import { act, fireEvent, render, screen } from '@testing-library/react-native';
import * as Notifications from 'expo-notifications';
import { I18nextProvider } from 'react-i18next';
import { Linking } from 'react-native';

import NotificationSettingsScreen from '@/app/notification-settings';
import { i18n } from '@/lib/i18n';
import { pushPath, showWhileOpen, turnOnPush } from '@/lib/push';
import { ToastProvider } from '@/ui';

const mockPost = jest.fn();
const mockDelete = jest.fn();
jest.mock('@/lib/api', () => ({ api: () => ({ POST: mockPost, DELETE: mockDelete }) }));
jest.mock('expo-router', () => ({
  router: { push: jest.fn(), back: jest.fn() },
  // Run focus effects once, like a screen coming into view.
  useFocusEffect: (effect: () => void) => jest.requireActual<typeof import('react')>('react').useEffect(effect, [effect]),
}));
jest.mock('@/lib/notifications', () => ({
  deviceTimezone: () => 'America/Chicago',
  useNotificationSettings: () => ({ settings: null, failed: false, save: jest.fn() }),
}));

const permissions = Notifications.getPermissionsAsync as jest.Mock;
const ask = Notifications.requestPermissionsAsync as jest.Mock;
const ok = { data: { phones: 1 }, error: undefined, response: { ok: true, status: 201 } };

const wrap = (ui: React.ReactElement) =>
  render(
    <I18nextProvider i18n={i18n}>
      <ToastProvider>{ui}</ToastProvider>
    </I18nextProvider>,
  );

beforeEach(() => {
  mockPost.mockReset().mockResolvedValue(ok);
  mockDelete.mockReset().mockResolvedValue({ response: { ok: true, status: 204 } });
  permissions.mockReset().mockResolvedValue({ granted: false, canAskAgain: true });
  ask.mockReset().mockResolvedValue({ granted: true });
});

describe('Push on the phone (B6.2b)', () => {
  it('opens only REOS pushes, and keeps a chat quiet while it is open', () => {
    expect(pushPath({ path: '/messages/c-1', kind: 'message' })).toBe('/messages/c-1');
    expect(pushPath({ kind: 'todo', title: 'Call Maria' })).toBeNull();
    expect(pushPath({ path: 'https://evil.example', kind: 'message' })).toBeNull();
    expect(showWhileOpen({ path: '/notifications', kind: 'state_went_live' })).toBe(true);
  });

  it('asks only when told to, then sends the token with this phone', async () => {
    expect(await turnOnPush(false)).toBe(false);
    expect(ask).not.toHaveBeenCalled();

    expect(await turnOnPush(true)).toBe(true);
    expect(mockPost).toHaveBeenCalledWith('/me/push-devices', {
      body: { token: 'ExponentPushToken[abcdefghijklmnop]', platform: 'ios', name: "Sofia's iPhone" },
    });
  });

  it('turns push on from Me › Notifications', async () => {
    await wrap(<NotificationSettingsScreen />);
    const button = await screen.findByRole('button', { name: 'Turn on notifications' });
    // Once allowed, the phone reports notifications on.
    permissions.mockResolvedValue({ granted: true, canAskAgain: true });
    ask.mockResolvedValue({ granted: true });
    await fireEvent.press(button);

    expect(await screen.findByText('Notifications are on for this phone.')).toBeOnTheScreen();
    expect(mockPost).toHaveBeenCalledWith('/me/push-devices', expect.anything());
  });

  it('turns it off on this phone', async () => {
    permissions.mockResolvedValue({ granted: true, canAskAgain: true });
    await wrap(<NotificationSettingsScreen />);
    expect(await screen.findByText('Notifications are on for this phone.')).toBeOnTheScreen();
    await fireEvent.press(screen.getByRole('button', { name: 'Turn off on this phone' }));
    await act(async () => undefined);
    expect(mockDelete).toHaveBeenCalledWith('/me/push-devices/current');
  });

  it('points to phone settings when notifications are blocked', async () => {
    permissions.mockResolvedValue({ granted: false, canAskAgain: false });
    const settings = jest.spyOn(Linking, 'openSettings').mockResolvedValue(undefined);
    await wrap(<NotificationSettingsScreen />);
    await fireEvent.press(await screen.findByRole('button', { name: 'Open phone settings' }));
    expect(settings).toHaveBeenCalled();
  });
});
