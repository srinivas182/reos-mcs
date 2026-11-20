import { createApiClient } from '@reos/api-client';
import { act, renderHook, waitFor } from '@testing-library/react-native';
import { createHash } from 'node:crypto';
import * as LocalAuthentication from 'expo-local-authentication';
import * as SecureStore from 'expo-secure-store';
import * as WebBrowser from 'expo-web-browser';
import type { ReactNode } from 'react';

import { apiBaseUrl, withSession } from '@/lib/api';
import { AuthProvider, useAuth } from '@/lib/auth';
import { maskPhone, normalisePhone } from '@/lib/phone';
import { saveToken } from '@/lib/session';

const store = (SecureStore as unknown as { __store: Map<string, string> }).__store;

const signInBody = (overrides: Record<string, unknown> = {}) => ({
  token: '7|device-token',
  token_type: 'Bearer',
  new_device: false,
  new_account: true,
  user: { id: 7, name: 'Sofia Reyes', email: 'sofia@example.com', email_verified: true, phone: null, has_password: false, sign_in_methods: ['google'], locale: 'en', appearance: 'system', text_size: 'standard' },
  ...overrides,
});

type Route = (request: Request) => Response | Promise<Response>;

/** An API client whose answers the test decides, with the app's session handling. */
function client(routes: Record<string, Route>) {
  const calls: Request[] = [];
  const fetch = jest.fn(async (request: Request) => {
    calls.push(request.clone());
    const path = new URL(request.url).pathname.replace('/api/v1', '');
    const route = routes[`${request.method} ${path}`];

    return route ? route(request) : new Response('{}', { status: 404 });
  });

  return { calls, api: withSession(createApiClient({ baseUrl: apiBaseUrl(), fetch })) };
}

const json = (body: unknown, status = 200) => new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });

async function setup(routes: Record<string, Route>) {
  const { api, calls } = client({ 'GET /me': () => json(signInBody().user), ...routes });
  const wrapper = ({ children }: { children: ReactNode }) => <AuthProvider client={api}>{children}</AuthProvider>;
  const hook = await renderHook(() => useAuth(), { wrapper });

  return { calls, result: hook.result };
}

beforeEach(() => {
  store.clear();
  jest.clearAllMocks();
});

describe('starting the app', () => {
  it('shows sign-in when there is no saved token', async () => {
    const { result } = await setup({});

    await waitFor(() => expect(result.current.status).toBe('signedOut'));
  });

  it('locks behind Face ID when it is turned on, and unlocks', async () => {
    await saveToken('7|saved');
    store.set('reos.biometric', '1');
    const { result } = await setup({});

    await waitFor(() => expect(result.current.status).toBe('locked'));
    await act(async () => {
      await result.current.unlock();
    });

    expect(result.current.status).toBe('signedIn');
  });

  it('signs out when the server no longer accepts the token', async () => {
    await saveToken('7|revoked');
    const { result } = await setup({ 'GET /me': () => json({ message: 'Unauthenticated.' }, 401) });

    await waitFor(() => expect(result.current.status).toBe('signedOut'));
    expect(store.has('reos.token')).toBe(false);
  });
});

describe('Google in the browser', () => {
  it('swaps the one-time code using the verifier behind the challenge it sent', async () => {
    let opened = '';
    jest.mocked(WebBrowser.openAuthSessionAsync).mockImplementation(async (url) => {
      opened = url;

      return { type: 'success', url: 'reos://auth?code=one-time-code' };
    });
    const { result, calls } = await setup({ 'POST /auth/exchange': () => json(signInBody()) });
    await waitFor(() => expect(result.current.status).toBe('signedOut'));

    let outcome;
    await act(async () => {
      outcome = await result.current.signInWithBrowser('google');
    });

    const challenge = new URL(opened).searchParams.get('app_challenge');
    const exchange = await calls.find((call) => call.url.endsWith('/auth/exchange'))!.json();
    const expected = createHash('sha256').update(exchange.code_verifier).digest('base64url');

    expect(opened).toMatch(/^http:\/\/localhost:8000\/auth\/google\/redirect\?app_challenge=/);
    expect(challenge).toBe(expected);
    expect(exchange).toMatchObject({ code: 'one-time-code', device: { name: "Sofia's iPhone", app_version: '1.0.0' } });
    expect(outcome).toEqual({ ok: true, newDevice: false, newAccount: true });
    expect(result.current.status).toBe('signedIn');
    expect(result.current.landing).toBe('/face-id');
    expect(store.get('reos.token')).toBe('7|device-token');
  });

  it('goes to the new-phone screen first on a new device', async () => {
    jest.mocked(WebBrowser.openAuthSessionAsync).mockResolvedValue({ type: 'success', url: 'reos://auth?code=c' });
    const { result } = await setup({ 'POST /auth/exchange': () => json(signInBody({ new_device: true })) });

    await act(async () => {
      await result.current.signInWithBrowser('google');
    });

    expect(result.current.landing).toBe('/new-device');
  });

  it('stays put when the person closes the browser', async () => {
    jest.mocked(WebBrowser.openAuthSessionAsync).mockResolvedValue({ type: WebBrowser.WebBrowserResultType.CANCEL });
    const { result } = await setup({});

    let outcome;
    await act(async () => {
      outcome = await result.current.signInWithBrowser('google');
    });

    expect(outcome).toEqual({ ok: false, cancelled: true });
  });

  it('explains a failure the server sent back', async () => {
    jest.mocked(WebBrowser.openAuthSessionAsync).mockResolvedValue({ type: 'success', url: 'reos://auth?error=identity.errors.unavailable' });
    const { result } = await setup({});

    let outcome;
    await act(async () => {
      outcome = await result.current.signInWithBrowser('google');
    });

    expect(outcome).toEqual({ ok: false, message: "This way of signing in isn't available yet. Try another one." });
  });
});

describe('phone codes', () => {
  it('asks people to wait when codes are requested too often', async () => {
    const { result } = await setup({ 'POST /auth/phone/start': () => json({}, 429) });

    let answer;
    await act(async () => {
      answer = await result.current.sendPhoneCode('+15125550100');
    });

    expect(answer).toEqual({ ok: false, message: 'Please wait a minute before asking for another code.' });
  });

  it('shows the server’s reason for a wrong code', async () => {
    const { result } = await setup({
      'POST /auth/phone/verify': () => json({ reason: 'identity.errors.wrongCode', message: 'x' }, 422),
    });

    let outcome;
    await act(async () => {
      outcome = await result.current.verifyPhoneCode('+15125550100', '000000');
    });

    expect(outcome).toEqual({ ok: false, message: "That code isn't right, or it has expired. Check it and try again." });
  });
});

it('skips the Face ID offer on phones without it', async () => {
  jest.mocked(LocalAuthentication.hasHardwareAsync).mockResolvedValueOnce(false);
  const { result } = await setup({ 'POST /auth/phone/verify': () => json(signInBody()) });

  await act(async () => {
    await result.current.verifyPhoneCode('+15125550100', '123456');
  });

  expect(result.current.landing).toBe('/');
});

it('signs out on the server and forgets the token', async () => {
  await saveToken('7|saved');
  const { result, calls } = await setup({ 'POST /auth/sign-out': () => new Response(null, { status: 204 }) });
  await waitFor(() => expect(result.current.status).toBe('signedIn'));

  await act(async () => {
    await result.current.signOut();
  });

  expect(calls.some((call) => call.url.endsWith('/auth/sign-out') && call.headers.get('Authorization') === 'Bearer 7|saved')).toBe(true);
  expect(result.current.status).toBe('signedOut');
  expect(store.has('reos.token')).toBe(false);
});

describe('phone numbers', () => {
  it.each([
    ['(512) 555-0100', '+15125550100'],
    ['1 512 555 0100', '+15125550100'],
    ['+44 20 7946 0958', '+442079460958'],
    ['555', null],
  ])('reads %s as %s', (input, expected) => {
    expect(normalisePhone(input)).toBe(expected);
  });

  it('shows only the last four digits', () => {
    expect(maskPhone('+17135550142')).toBe('(713) •••-0142');
    expect(maskPhone('+442079460958')).toBe('+44 ••••0958');
  });
});
