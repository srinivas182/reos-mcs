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

