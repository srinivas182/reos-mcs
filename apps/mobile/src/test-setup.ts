/**
 * Jest setup for the mobile app: Unistyles' official mocks with our themes
 * registered, in-memory MMKV, and quiet native modules.
 */
import 'react-native-unistyles/mocks';
// Registers the REOS themes with the mocked Unistyles runtime (jest.mock calls below are hoisted above imports).
import '@/ui/unistyles';

jest.mock('react-native-mmkv', () => {
  const stores = new Map<string, Map<string, string | boolean>>();

  return {
    createMMKV: ({ id }: { id: string }) => {
      const store = stores.get(id) ?? new Map<string, string | boolean>();
      stores.set(id, store);

      return {
        getString: (key: string) => {
          const value = store.get(key);

          return typeof value === 'string' ? value : undefined;
        },
        getBoolean: (key: string) => {
          const value = store.get(key);

          return typeof value === 'boolean' ? value : undefined;
        },
        set: (key: string, value: string | boolean) => store.set(key, value),
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

jest.mock('expo-web-browser', () => ({ openAuthSessionAsync: jest.fn(), openBrowserAsync: jest.fn(), WebBrowserResultType: { CANCEL: 'cancel', DISMISS: 'dismiss' } }));

jest.mock('expo-apple-authentication', () => ({
  signInAsync: jest.fn(),
  AppleAuthenticationScope: { FULL_NAME: 0, EMAIL: 1 },
  AppleAuthenticationButton: () => null,
  AppleAuthenticationButtonType: { CONTINUE: 1 },
  AppleAuthenticationButtonStyle: { BLACK: 2, WHITE: 0 },
}));

jest.mock('expo-device', () => ({ deviceName: "Sofia's iPhone", modelName: 'iPhone 16', isDevice: true }));
jest.mock('expo-application', () => ({ nativeApplicationVersion: '1.0.0' }));

jest.mock('expo-notifications', () => ({
  getPermissionsAsync: jest.fn().mockResolvedValue({ granted: false }),
  requestPermissionsAsync: jest.fn().mockResolvedValue({ granted: true }),
  setNotificationChannelAsync: jest.fn(),
  setNotificationCategoryAsync: jest.fn().mockResolvedValue(undefined),
  setNotificationHandler: jest.fn(),
  addNotificationResponseReceivedListener: jest.fn(() => ({ remove: jest.fn() })),
  useLastNotificationResponse: jest.fn(() => null),
  DEFAULT_ACTION_IDENTIFIER: 'expo.modules.notifications.actions.DEFAULT',
  getExpoPushTokenAsync: jest.fn().mockResolvedValue({ type: 'expo', data: 'ExponentPushToken[abcdefghijklmnop]' }),
  dismissNotificationAsync: jest.fn().mockResolvedValue(undefined),
  cancelScheduledNotificationAsync: jest.fn().mockResolvedValue(undefined),
  scheduleNotificationAsync: jest.fn().mockResolvedValue('id'),
  AndroidImportance: { DEFAULT: 3, HIGH: 4 },
  SchedulableTriggerInputTypes: { DAILY: 'daily', DATE: 'date' },
}));

// B4.3b voice to-dos: speech recognition, contacts and the home-screen
// shortcut. Tests drive speech through `__emit` and set contacts per case.
jest.mock('expo-speech-recognition', () => {
  const { useEffect } = jest.requireActual<typeof import('react')>('react');
  const handlers = new Map<string, Set<(event: unknown) => void>>();

  return {
    ExpoSpeechRecognitionModule: {
      isRecognitionAvailable: jest.fn(() => true),
      supportsOnDeviceRecognition: jest.fn(() => true),
      requestPermissionsAsync: jest.fn(async () => ({ granted: true })),
      start: jest.fn(),
      stop: jest.fn(() => handlers.get('end')?.forEach((handler) => handler(null))),
    },
    useSpeechRecognitionEvent: (name: string, handler: (event: unknown) => void) => {
      useEffect(() => {
        const set = handlers.get(name) ?? new Set();
        handlers.set(name, set);
        set.add(handler);

        return () => {
          set.delete(handler);
        };
      }, [name, handler]);
    },
    __emit: (name: string, event: unknown) => handlers.get(name)?.forEach((handler) => handler(event)),
  };
});

jest.mock('expo-contacts', () => ({
  getPermissionsAsync: jest.fn(async () => ({ granted: true, canAskAgain: true })),
  requestPermissionsAsync: jest.fn(async () => ({ granted: true, canAskAgain: true })),
  ContactField: { FULL_NAME: 'fullName', GIVEN_NAME: 'givenName', FAMILY_NAME: 'familyName', EMAILS: 'emails', PHONES: 'phones', BIRTHDAY: 'birthday' },
  Contact: { getAllDetails: jest.fn(async () => []) },
}));

jest.mock('expo-quick-actions', () => ({ setItems: jest.fn().mockResolvedValue(undefined), addListener: jest.fn(() => ({ remove: jest.fn() })), initial: undefined }));
// Lesson video (A5.3b): a player whose position tests can read.
jest.mock('expo-video', () => {
  const { View } = jest.requireActual('react-native');
  const { createElement } = jest.requireActual('react');
  const player = {
    currentTime: 0,
    play: jest.fn(),
    pause: jest.fn(),
    seekBy(seconds: number) {
      player.currentTime += seconds;
    },
  };

  return {
    __player: player,
    useVideoPlayer: jest.fn((_source: unknown, setup?: (p: typeof player) => void) => {
      setup?.(player);

      return player;
    }),
    VideoView: (props: object) => createElement(View, { testID: 'video', ...props }),
  };
});
jest.mock('expo-quick-actions/router', () => ({ useQuickActionRouting: jest.fn() }));
// Live updates (B6.1) need Reverb; screens are tested without a socket.
jest.mock('@/lib/realtime', () => ({ listen: () => () => undefined, disconnectRealtime: jest.fn() }));

// Video sessions (B6.3): the native WebRTC modules aren't in Jest; a room renders its children.
jest.mock('@livekit/react-native', () => ({
  registerGlobals: jest.fn(),
  AudioSession: { startAudioSession: jest.fn().mockResolvedValue(undefined), stopAudioSession: jest.fn().mockResolvedValue(undefined) },
  LiveKitRoom: ({ children }: { children: unknown }) => children,
  VideoTrack: () => null,
  useTracks: () => [],
  isTrackReference: () => false,
}));
jest.mock('livekit-client', () => ({ Track: { Source: { Camera: 'camera', ScreenShare: 'screen_share' } } }));
