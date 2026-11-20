/**
 * Jest setup for the mobile app: Unistyles' official mocks with our themes
 * registered, in-memory MMKV, and quiet native modules.
 */
import 'react-native-unistyles/mocks';
// Registers the REOS themes with the mocked Unistyles runtime (jest.mock calls below are hoisted above imports).
import '@/ui/unistyles';

jest.mock('react-native-mmkv', () => {
  const stores = new Map<string, Map<string, string>>();

  return {
    createMMKV: ({ id }: { id: string }) => {
      const store = stores.get(id) ?? new Map<string, string>();
      stores.set(id, store);

      return {
        getString: (key: string) => store.get(key),
        set: (key: string, value: string) => store.set(key, value),
        remove: (key: string) => store.delete(key),
        clearAll: () => store.clear(),
      };
    },
  };
});

jest.mock('expo-haptics', () => ({
  impactAsync: jest.fn(),
  ImpactFeedbackStyle: { Light: 'light', Medium: 'medium', Heavy: 'heavy' },
}));

jest.mock('expo-network', () => ({
  useNetworkState: jest.fn(() => ({ isConnected: true, isInternetReachable: true })),
}));


// A3.1 sign-in: in-memory stand-ins for the phone's secure storage, crypto,
// biometrics, browser sign-in and Apple's sheet. Tests change them per case.
jest.mock('expo-secure-store', () => {
  const store = new Map<string, string>();

  return {
    WHEN_UNLOCKED_THIS_DEVICE_ONLY: 'whenUnlockedThisDeviceOnly',
    getItemAsync: jest.fn(async (key: string) => store.get(key) ?? null),
    setItemAsync: jest.fn(async (key: string, value: string) => void store.set(key, value)),
    deleteItemAsync: jest.fn(async (key: string) => void store.delete(key)),
    __store: store,
  };
});

jest.mock('expo-crypto', () => {
  const crypto = jest.requireActual<typeof import('node:crypto')>('node:crypto');

  return {
    randomUUID: () => crypto.randomUUID(),
    getRandomBytes: (length: number) => new Uint8Array(crypto.randomBytes(length)),
    digestStringAsync: async (_algorithm: string, value: string) => crypto.createHash('sha256').update(value).digest('base64'),
    CryptoDigestAlgorithm: { SHA256: 'SHA-256' },
    CryptoEncoding: { BASE64: 'base64' },
  };
});

jest.mock('expo-local-authentication', () => ({
  hasHardwareAsync: jest.fn(async () => true),
  isEnrolledAsync: jest.fn(async () => true),
  authenticateAsync: jest.fn(async () => ({ success: true })),
}));

jest.mock('expo-web-browser', () => ({ openAuthSessionAsync: jest.fn(), WebBrowserResultType: { CANCEL: 'cancel', DISMISS: 'dismiss' } }));

jest.mock('expo-apple-authentication', () => ({
  signInAsync: jest.fn(),
  AppleAuthenticationScope: { FULL_NAME: 0, EMAIL: 1 },
  AppleAuthenticationButton: () => null,
  AppleAuthenticationButtonType: { CONTINUE: 1 },
  AppleAuthenticationButtonStyle: { BLACK: 2, WHITE: 0 },
}));

jest.mock('expo-device', () => ({ deviceName: "Sofia's iPhone", modelName: 'iPhone 16' }));
jest.mock('expo-application', () => ({ nativeApplicationVersion: '1.0.0' }));
