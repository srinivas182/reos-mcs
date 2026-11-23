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
  useNetworkState: jest.fn(() => ({ isConnected: true, isInternetReachable: true, type: 'WIFI' })),
  getNetworkStateAsync: jest.fn(async () => ({ isConnected: true, isInternetReachable: true, type: 'WIFI' })),
  NetworkStateType: { NONE: 'NONE', UNKNOWN: 'UNKNOWN', CELLULAR: 'CELLULAR', WIFI: 'WIFI' },
}));

// Offline lessons (A8.2b): files in memory; tests read `__files`.
jest.mock('expo-file-system', () => {
  const files = new Map<string, number>();
  class File {
    uri: string;
    constructor(...parts: (string | { uri: string })[]) {
      this.uri = parts.map((p) => (typeof p === 'string' ? p : p.uri)).join('/');
    }
    get exists() {
      return files.has(this.uri);
    }
    get size() {
      return files.get(this.uri) ?? 0;
    }
    delete() {
      files.delete(this.uri);
    }
    static downloadFileAsync = jest.fn(async (_url: string, destination: File) => {
      files.set(destination.uri, 1000);

      return destination;
    });
  }
  class Directory extends File {
    get exists() {
      return true;
    }
    create() {}
  }

  return { File, Directory, Paths: { document: { uri: 'file:///documents' } }, __files: files };
});


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
    digest: async (_algorithm: string, data: Uint8Array) => {
      const out = crypto.createHash('sha256').update(Buffer.from(data)).digest();

      return out.buffer.slice(out.byteOffset, out.byteOffset + out.byteLength);
    },
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
    playing: false,
    playbackRate: 1,
    staysActiveInBackground: false,
    showNowPlayingNotification: false,
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

// Hands-free mode (A9.3): the phone's voice speaks at once and calls onDone;
// the lock-screen playlist records what it's asked to do. Tests read `__spoken`.
jest.mock('expo-speech', () => {
  const spoken: string[] = [];

  return {
    __spoken: spoken,
    speak: jest.fn((text: string, options?: { onDone?: () => void }) => {
      spoken.push(text);
      setTimeout(() => options?.onDone?.(), 0);
    }),
    stop: jest.fn(async () => undefined),
  };
});
jest.mock('expo-audio', () => {
  const listeners: Record<string, ((payload: unknown) => void) | undefined> = {};
  const playlist = {
    addListener: jest.fn((name: string, handler: (payload: unknown) => void) => {
      listeners[name] = handler;

      return { remove: () => delete listeners[name] };
    }),
    setActiveForLockScreen: jest.fn(),
    updateLockScreenMetadata: jest.fn(),
    clearLockScreenControls: jest.fn(),
    play: jest.fn(),
    pause: jest.fn(),
    skipTo: jest.fn(),
    destroy: jest.fn(),
  };

  return {
    __playlist: playlist,
    __emit: (name: string, payload: unknown) => listeners[name]?.(payload),
    createAudioPlaylist: jest.fn(() => playlist),
    setAudioModeAsync: jest.fn(async () => undefined),
  };
});
