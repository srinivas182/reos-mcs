/**
 * Signing in on the phone (A3.1, journey screens Sign in, Phone code, Face ID,
 * New phone). One context holds who is signed in; screens call its actions.
 *
 * - Apple on iPhone: native sheet, ID token to `/auth/apple`.
 * - Google, and Apple on Android or on demo data: the web sign-in in the
 *   system browser with a PKCE challenge; the browser returns to
 *   `reos://auth?code=…` and only this app (holding the verifier) can swap the
 *   code for a token at `/auth/exchange`.
 * - Phone: a texted code, `/auth/phone/start` then `/auth/phone/verify`.
 */
import type { ApiClient, Schemas } from '@reos/api-client';
import * as AppleAuthentication from 'expo-apple-authentication';
import * as Crypto from 'expo-crypto';
import * as LocalAuthentication from 'expo-local-authentication';
import * as WebBrowser from 'expo-web-browser';
import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';
import { Platform } from 'react-native';

import { api, webBaseUrl } from '@/lib/api';
import { i18n } from '@/lib/i18n';
import { biometricAsked, biometricEnabled, clearToken, deviceInfo, loadToken, onSessionExpired, saveToken } from '@/lib/session';

export type Me = Schemas['MeResource'];
export type SignInOptions = Schemas['AppConfigResource']['sign_in'];
type SignInResponse = Schemas['SignInResource'];

export type AuthStatus = 'loading' | 'signedOut' | 'locked' | 'signedIn';

/** Where to go right after signing in (journey: new phone, then the Face ID offer, then Today). */
export type Landing = '/new-device' | '/face-id' | '/';

/** What a sign-in attempt ended with; `message` is ready to show. */
export type Outcome = { ok: true; newDevice: boolean; newAccount: boolean } | { ok: false; message: string } | { ok: false; cancelled: true };

type AuthContextValue = {
  status: AuthStatus;
  user: Me | null;
  /** Set by a sign-in; the navigation gate goes there once and clears it. */
  landing: Landing | null;
  clearLanding: () => void;
  signInWithApple: (demo: boolean) => Promise<Outcome>;
  signInWithBrowser: (provider: 'google' | 'apple') => Promise<Outcome>;
  sendPhoneCode: (phone: string) => Promise<{ ok: true } | { ok: false; message: string }>;
  verifyPhoneCode: (phone: string, code: string) => Promise<Outcome>;
  unlock: () => Promise<boolean>;
  refresh: () => Promise<void>;
  signOut: () => Promise<void>;
};

const AuthContext = createContext<AuthContextValue | null>(null);

/** Turns an API error body into words for the person (server reasons are translation keys). */
export function errorMessage(body: unknown): string {
  const reason = typeof body === 'object' && body !== null && 'reason' in body ? String((body as { reason: unknown }).reason) : '';
  const message = typeof body === 'object' && body !== null && 'message' in body ? String((body as { message: unknown }).message) : '';

  if (reason && i18n.exists(reason)) {
    return i18n.t(reason as 'identity.errors.invalidToken');
  }

  return message || i18n.t('identity.errors.invalidToken');
}

const base64Url = (value: string): string => value.replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');

/** PKCE pair: a random verifier kept in memory and its SHA-256 challenge. */
export async function pkcePair(): Promise<{ verifier: string; challenge: string }> {
  const bytes = Crypto.getRandomBytes(48);
  const verifier = base64Url(btoa(String.fromCharCode(...bytes)));
  const digest = await Crypto.digestStringAsync(Crypto.CryptoDigestAlgorithm.SHA256, verifier, { encoding: Crypto.CryptoEncoding.BASE64 });

  return { verifier, challenge: base64Url(digest) };
}

/** Face ID or fingerprint is set up on this phone and hasn't been offered yet. */
export async function biometricAvailable(): Promise<boolean> {
  return (await LocalAuthentication.hasHardwareAsync()) && (await LocalAuthentication.isEnrolledAsync());
}

async function shouldOfferBiometric(): Promise<boolean> {
  return !(await biometricAsked()) && (await biometricAvailable());
}

export function AuthProvider({ children, client = api() }: { children: ReactNode; client?: ApiClient }) {
  const [status, setStatus] = useState<AuthStatus>('loading');
  const [user, setUser] = useState<Me | null>(null);
  const [landing, setLanding] = useState<Landing | null>(null);

  const refresh = useCallback(async () => {
    const { data, response } = await client.GET('/me');

    if (response.ok && data !== undefined) {
      setUser(data);
    }
  }, [client]);

  // Launch: a saved token means signed in (behind Face ID if turned on).
  useEffect(() => {
    let active = true;

    void (async () => {
      const token = await loadToken();

      if (!active) {
        return;
      }

      if (token === null) {
        setStatus('signedOut');

        return;
      }

      setStatus((await biometricEnabled()) ? 'locked' : 'signedIn');
      await refresh().catch(() => undefined);
    })();

    return () => {
      active = false;
    };
  }, [refresh]);

  useEffect(
    () =>
      onSessionExpired(() => {
        setUser(null);
        setStatus('signedOut');
      }),
    [],
  );

  const finish = useCallback(async (data: SignInResponse): Promise<Outcome> => {
    await saveToken(data.token);
    setUser(data.user);
    setLanding(data.new_device ? '/new-device' : (await shouldOfferBiometric()) ? '/face-id' : '/');
    setStatus('signedIn');

    return { ok: true, newDevice: data.new_device, newAccount: data.new_account };
  }, []);

  const signInWithBrowser = useCallback(
    async (provider: 'google' | 'apple'): Promise<Outcome> => {
      const { verifier, challenge } = await pkcePair();
      const result = await WebBrowser.openAuthSessionAsync(`${webBaseUrl()}/auth/${provider}/redirect?app_challenge=${challenge}`, 'reos://auth');

      if (result.type !== 'success') {
        return { ok: false, cancelled: true };
      }

      const answer = new URL(result.url).searchParams;
      const error = answer.get('error');

      if (error !== null) {
        return { ok: false, message: errorMessage({ reason: error }) };
      }

      const { data, error: failure } = await client.POST('/auth/exchange', {
        body: { code: answer.get('code') ?? '', code_verifier: verifier, device: await deviceInfo() },
      });

      return data !== undefined ? finish(data) : { ok: false, message: errorMessage(failure) };
    },
    [client, finish],
  );

  const signInWithApple = useCallback(
    async (demo: boolean): Promise<Outcome> => {
      // Android has no Apple sheet, and demo data uses the browser's persona picker.
      if (Platform.OS !== 'ios' || demo) {
        return signInWithBrowser('apple');
      }

      try {
        const credential = await AppleAuthentication.signInAsync({
          requestedScopes: [AppleAuthentication.AppleAuthenticationScope.FULL_NAME, AppleAuthentication.AppleAuthenticationScope.EMAIL],
        });
        const name = [credential.fullName?.givenName, credential.fullName?.familyName].filter(Boolean).join(' ') || null;
        const { data, error } = await client.POST('/auth/{provider}', {
          params: { path: { provider: 'apple' } },
          body: { id_token: credential.identityToken ?? '', name, device: await deviceInfo() },
        });

        return data !== undefined ? finish(data) : { ok: false, message: errorMessage(error) };
      } catch (error) {
        if (typeof error === 'object' && error !== null && 'code' in error && error.code === 'ERR_REQUEST_CANCELED') {
          return { ok: false, cancelled: true };
        }

        return { ok: false, message: errorMessage(null) };
      }
    },
    [client, finish, signInWithBrowser],
  );

  const sendPhoneCode = useCallback(
    async (phone: string): Promise<{ ok: true } | { ok: false; message: string }> => {
      const { response, error } = await client.POST('/auth/phone/start', { body: { phone } });

      if (response.status === 429) {
        return { ok: false, message: i18n.t('mobile.signIn.code.wait') };
      }

      return response.ok ? { ok: true } : { ok: false, message: errorMessage(error) };
    },
    [client],
  );

  const verifyPhoneCode = useCallback(
    async (phone: string, code: string): Promise<Outcome> => {
      const { data, error } = await client.POST('/auth/phone/verify', { body: { phone, code, device: await deviceInfo() } });

      return data !== undefined ? finish(data) : { ok: false, message: errorMessage(error) };
    },
    [client, finish],
  );

  const unlock = useCallback(async (): Promise<boolean> => {
    const result = await LocalAuthentication.authenticateAsync({
      promptMessage: i18n.t('mobile.biometric.prompt'),
      cancelLabel: i18n.t('common.cancel'),
    });

    if (result.success) {
      setStatus('signedIn');
    }

    return result.success;
  }, []);

  const signOut = useCallback(async () => {
    await client.POST('/auth/sign-out').catch(() => undefined);
    await clearToken();
    setUser(null);
    setStatus('signedOut');
  }, [client]);

  const clearLanding = useCallback(() => setLanding(null), []);

  const value = useMemo(
    () => ({ status, user, landing, clearLanding, signInWithApple, signInWithBrowser, sendPhoneCode, verifyPhoneCode, unlock, refresh, signOut }),
    [status, user, landing, clearLanding, signInWithApple, signInWithBrowser, sendPhoneCode, verifyPhoneCode, unlock, refresh, signOut],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const value = useContext(AuthContext);

  if (value === null) {
    throw new Error('useAuth must be used inside <AuthProvider>');
  }

  return value;
}
