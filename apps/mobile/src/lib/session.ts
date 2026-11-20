/**
 * The device's sign-in (A3.1): the bearer token in the phone's secure storage
 * (Keychain or Keystore), a random id for this installation, and whether Face
 * ID or fingerprint opens the app. Nothing biometric leaves the phone.
 */
import * as Application from 'expo-application';
import * as Crypto from 'expo-crypto';
import * as Device from 'expo-device';
import * as SecureStoreNative from 'expo-secure-store';
import { Platform } from 'react-native';

/**
 * The phone's Keychain or Keystore. The browser preview (development only;
 * REOS ships to phones) has no secure store, so it uses local storage.
 */
const SecureStore =
  Platform.OS === 'web'
    ? {
        WHEN_UNLOCKED_THIS_DEVICE_ONLY: SecureStoreNative.WHEN_UNLOCKED_THIS_DEVICE_ONLY,
        getItemAsync: async (key: string) => globalThis.localStorage?.getItem(key) ?? null,
        setItemAsync: async (key: string, value: string, _options?: unknown) => globalThis.localStorage?.setItem(key, value),
        deleteItemAsync: async (key: string) => globalThis.localStorage?.removeItem(key),
      }
    : SecureStoreNative;

const TOKEN = 'reos.token';
const DEVICE_ID = 'reos.device-id';
const BIOMETRIC = 'reos.biometric';
const BIOMETRIC_ASKED = 'reos.biometric-asked';

let token: string | null = null;
const listeners = new Set<() => void>();

export type DeviceInfo = {
  id: string;
  name: string;
  platform: 'ios' | 'android';
  app_version?: string;
};

/** Reads the saved token once at launch. */
export async function loadToken(): Promise<string | null> {
  token = await SecureStore.getItemAsync(TOKEN);

  return token;
}

export const currentToken = (): string | null => token;

export async function saveToken(value: string): Promise<void> {
  token = value;
  await SecureStore.setItemAsync(TOKEN, value, { keychainAccessible: SecureStore.WHEN_UNLOCKED_THIS_DEVICE_ONLY });
}

export async function clearToken(): Promise<void> {
  token = null;
  await SecureStore.deleteItemAsync(TOKEN);
}

/** Called when the server says the token no longer works (signed out elsewhere, or 30 days idle). */
export function onSessionExpired(listener: () => void): () => void {
  listeners.add(listener);

  return () => listeners.delete(listener);
}

export function expireSession(): void {
  token = null;
  void SecureStore.deleteItemAsync(TOKEN);
  listeners.forEach((listener) => listener());
}

/** This installation, as "Your devices" shows it. The id is made once and kept. */
export async function deviceInfo(): Promise<DeviceInfo> {
  let id = await SecureStore.getItemAsync(DEVICE_ID);

  if (id === null) {
    id = Crypto.randomUUID();
    await SecureStore.setItemAsync(DEVICE_ID, id);
  }

  return {
    id,
    name: (Device.deviceName ?? Device.modelName ?? (Platform.OS === 'ios' ? 'iPhone' : 'Android phone')).slice(0, 100),
    platform: Platform.OS === 'ios' ? 'ios' : 'android',
    app_version: Application.nativeApplicationVersion ?? undefined,
  };
}

export async function biometricEnabled(): Promise<boolean> {
  return (await SecureStore.getItemAsync(BIOMETRIC)) === '1';
}

export async function setBiometricEnabled(enabled: boolean): Promise<void> {
  await SecureStore.setItemAsync(BIOMETRIC, enabled ? '1' : '0');
  await SecureStore.setItemAsync(BIOMETRIC_ASKED, '1');
}

/** The Face ID offer shows once per device, after the first sign-in. */
export async function biometricAsked(): Promise<boolean> {
  return (await SecureStore.getItemAsync(BIOMETRIC_ASKED)) === '1';
}
