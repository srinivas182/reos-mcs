import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import * as WebBrowser from 'expo-web-browser';
import { I18nextProvider } from 'react-i18next';

import HomeScreen from '@/app/index';
import ReconnectScreen from '@/app/reconnect/[id]';
import { type Connection, connectTool, toolInitials } from '@/lib/connections';
import { i18n } from '@/lib/i18n';
import { dayAndMonth, timeAgo } from '@/lib/time';
import { ToastProvider } from '@/ui';

const twoDaysAgo = new Date(Date.now() - 2 * 86_400_000).toISOString();

const jamesCalendar = (overrides: Partial<Connection> = {}): Connection =>
  ({
    id: 7,
    integration: 'google-workspace',
    integration_name: 'Google Calendar and Gmail',
    owner: 'user',
    account: 'james.miller@demo.reos.test',
    status: 'reconnect_needed',
    demo: true,
    scopes: [],
    history: '3m',
    last_synced_at: '2026-11-19T15:00:00Z',
    problem: 'connections.errors.refused',
    problem_since: twoDaysAgo,
    connected_at: '2026-11-01T10:00:00Z',
    ...overrides,
  }) as Connection;

const mockPost = jest.fn();
const mockState = { connections: [jamesCalendar()] as Connection[], reload: jest.fn() };

jest.mock('expo-router', () => ({
  router: { push: jest.fn(), back: jest.fn() },
  useLocalSearchParams: () => ({ id: '7' }),
  useFocusEffect: jest.fn(),
}));
jest.mock('@/lib/auth', () => ({ useAuth: () => ({ user: { name: 'James Miller' }, status: 'signedIn' }) }));
jest.mock('@/lib/api', () => ({
  api: () => ({ POST: mockPost }),
  fetchHealth: jest.fn().mockResolvedValue({ status: 'ok', app: 'REOS', version: '0.1.0-dev', time: 'now' }),
}));
jest.mock('@/lib/connections', () => {
  const actual = jest.requireActual('@/lib/connections');

  return { ...actual, useConnections: () => mockState };
});

const renderScreen = (ui: React.ReactElement) =>
  render(
    <I18nextProvider i18n={i18n}>
      <ToastProvider>{ui}</ToastProvider>
    </I18nextProvider>,
  );

beforeEach(() => {
  jest.clearAllMocks();
  mockState.connections = [jamesCalendar()];
});
afterEach(() => i18n.changeLanguage('en'));

describe('connecting a tool in the browser sheet', () => {
  it('opens the sign-in page and reads the outcome REOS sends back', async () => {
    mockPost.mockResolvedValue({ data: { authorization_url: 'https://accounts.example/auth?state=s' } });
    jest.mocked(WebBrowser.openAuthSessionAsync).mockResolvedValue({ type: 'success', url: 'reos://connections?outcome=connected&integration=google-workspace' });

    await expect(connectTool('google-workspace')).resolves.toEqual({ outcome: 'connected', reason: null });
    expect(mockPost).toHaveBeenCalledWith('/connections', { body: { integration: 'google-workspace' } });
    expect(WebBrowser.openAuthSessionAsync).toHaveBeenCalledWith('https://accounts.example/auth?state=s', 'reos://connections');
  });

  it('treats closing the sheet as cancelled, and passes on refusals', async () => {
    mockPost.mockResolvedValue({ data: { authorization_url: 'https://accounts.example/auth' } });
    jest.mocked(WebBrowser.openAuthSessionAsync).mockResolvedValueOnce({ type: WebBrowser.WebBrowserResultType.CANCEL });
    await expect(connectTool('google-workspace')).resolves.toEqual({ outcome: 'cancelled', reason: null });

    jest.mocked(WebBrowser.openAuthSessionAsync).mockResolvedValueOnce({ type: 'success', url: 'reos://connections?outcome=failed&reason=connections.errors.refused' });
    await expect(connectTool('google-workspace')).resolves.toEqual({ outcome: 'failed', reason: 'connections.errors.refused' });

    mockPost.mockResolvedValue({ error: { message: "This tool can't be connected right now." } });
    await expect(connectTool('google-workspace')).resolves.toEqual({ outcome: 'failed', reason: "This tool can't be connected right now." });
  });

  it('makes two-letter tiles and friendly times', () => {
    expect(toolInitials('Google Calendar and Gmail')).toBe('GC');
    expect(toolInitials('dotloop')).toBe('D');
    expect(timeAgo('2026-11-19T12:00:00Z', 'en', new Date('2026-11-21T12:00:00Z'))).toBe('2 days ago');
    expect(timeAgo('2026-11-19T12:00:00Z', 'es', new Date('2026-11-21T12:00:00Z'))).toBe('Anteayer');
    expect(dayAndMonth('2026-11-19T15:00:00Z', 'en')).toBe('November 19');
  });
});

describe('When a sync breaks (Connections and Sync, screen 26)', () => {
  it('puts a red card at the top of Today that opens the reconnect screen', async () => {
    const { router } = jest.requireMock('expo-router') as { router: { push: jest.Mock } };
    await renderScreen(<HomeScreen />);

    expect(screen.getByText('Google Calendar and Gmail stopped syncing')).toBeOnTheScreen();
    expect(screen.getByText('2 days ago. Your Google Calendar and Gmail data in REOS may be out of date.')).toBeOnTheScreen();
    await fireEvent.press(screen.getByRole('button', { name: 'Reconnect · 30 seconds' }));
    expect(router.push).toHaveBeenCalledWith({ pathname: '/reconnect/[id]', params: { id: '7' } });
  });

  it('shows nothing when everything syncs', async () => {
    mockState.connections = [jamesCalendar({ status: 'active', problem: null, problem_since: null })];
    await renderScreen(<HomeScreen />);

    expect(screen.queryByText(/stopped syncing/)).toBeNull();
  });
});

describe('Reconnect (Connections and Sync, screen 27)', () => {
  it('explains why and what happens next, then signs in again', async () => {
    const { router } = jest.requireMock('expo-router') as { router: { back: jest.Mock } };
    mockPost.mockResolvedValue({ data: { authorization_url: 'https://accounts.example/auth' } });
    jest.mocked(WebBrowser.openAuthSessionAsync).mockResolvedValue({ type: 'success', url: 'reos://connections?outcome=connected' });
    await renderScreen(<ReconnectScreen />);

    expect(screen.getByRole('header', { name: 'Reconnect Google Calendar and Gmail' })).toBeOnTheScreen();
    expect(screen.getByText('Access expired')).toBeOnTheScreen();
    expect(screen.getByText('Usually after a password change, or when Google Calendar and Gmail asks you to sign in again.')).toBeOnTheScreen();
    expect(screen.getByText('REOS catches up on everything since November 19')).toBeOnTheScreen();

    await fireEvent.press(screen.getByRole('button', { name: 'Sign in to Google Calendar and Gmail' }));

    await waitFor(() => expect(router.back).toHaveBeenCalled());
    expect(mockState.reload).toHaveBeenCalled();
    expect(screen.getByText('Google Calendar and Gmail is connected. Your data is syncing now.')).toBeOnTheScreen();
  });

  it('says what went wrong when the tool refuses again', async () => {
    mockPost.mockResolvedValue({ data: { authorization_url: 'https://accounts.example/auth' } });
    jest.mocked(WebBrowser.openAuthSessionAsync).mockResolvedValue({ type: 'success', url: 'reos://connections?outcome=failed&reason=connections.errors.refused' });
    await renderScreen(<ReconnectScreen />);

    await fireEvent.press(screen.getByRole('button', { name: 'Sign in to Google Calendar and Gmail' }));

    await waitFor(() => expect(screen.getByText("The tool didn't accept the sign-in. Please connect again.")).toBeOnTheScreen());
  });

  it('speaks Spanish', async () => {
    await i18n.changeLanguage('es');
    await renderScreen(<ReconnectScreen />);

    expect(screen.getByText('El acceso venció')).toBeOnTheScreen();
    expect(screen.getByText('REOS se pone al día con todo desde el 19 de noviembre')).toBeOnTheScreen();
  });
});
