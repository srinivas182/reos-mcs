import { fireEvent, render, screen } from '@testing-library/react-native';
import { I18nextProvider } from 'react-i18next';

import NotificationSettingsScreen from '@/app/notification-settings';
import NotificationsScreen from '@/app/notifications';
import { i18n } from '@/lib/i18n';
import { ToastProvider } from '@/ui';

const mockRead = jest.fn();
const mockSave = jest.fn().mockResolvedValue(true);
jest.mock('expo-router', () => ({ router: { push: jest.fn(), back: jest.fn() }, useFocusEffect: jest.fn() }));
jest.mock('@/lib/notifications', () => ({
  deviceTimezone: () => 'America/Chicago',
  useNotificationInbox: () => ({
    inbox: {
      unread: 1,
      items: [
        { id: 'n-1', kind: 'announcement', title: 'Office closed Friday afternoon', body: 'The office closes at 1 PM.', url: 'http://localhost/messages', read: false, at: new Date().toISOString() },
        { id: 'n-2', kind: 'purchase_receipt', title: 'Your receipt for Texas Pre-licence', body: null, url: null, read: true, at: new Date().toISOString() },
      ],
    },
    read: mockRead,
    reload: jest.fn(),
  }),
  useNotificationSettings: () => ({
    settings: {
      kinds: [
        { kind: 'announcement', group: 'messages', locked: false, urgent: false, digestible: true, channels: { inbox: true, email: false }, digest: null },
        { kind: 'new_device', group: 'account', locked: true, urgent: true, digestible: false, channels: { inbox: true, email: true }, digest: null },
      ],
      channels: ['inbox', 'email'],
      quiet: { enabled: true, start: '21:00', end: '07:00' },
      timezone: 'America/Chicago',
    },
    failed: false,
    save: mockSave,
  }),
}));

const wrap = (ui: React.ReactElement) =>
  render(
    <I18nextProvider i18n={i18n}>
      <ToastProvider>{ui}</ToastProvider>
    </I18nextProvider>,
  );

describe('Notifications on the phone (B6.2)', () => {
  it('lists the inbox, opens one and marks all read', async () => {
    const { router } = jest.requireMock('expo-router') as { router: { push: jest.Mock } };
    await wrap(<NotificationsScreen />);
    await fireEvent.press(screen.getByLabelText(/Office closed Friday afternoon\. The office closes at 1 PM\.\. unread/));
    expect(mockRead).toHaveBeenCalledWith('n-1');
    expect(router.push).toHaveBeenCalledWith('/messages');
    await fireEvent.press(screen.getByRole('button', { name: 'Mark all read' }));
    expect(mockRead).toHaveBeenCalledWith();
  });

  it('sets quiet hours in the phone time zone and channels per kind', async () => {
    await wrap(<NotificationSettingsScreen />);
    expect(screen.getByText('Times are in America/Chicago.')).toBeOnTheScreen();
    await fireEvent.changeText(screen.getByLabelText('Until'), '06:30');
    await fireEvent.press(screen.getByRole('button', { name: 'Save quiet hours' }));
    expect(mockSave).toHaveBeenCalledWith({ quiet: { enabled: true, start: '21:00', end: '06:30' }, timezone: 'America/Chicago' });

    await fireEvent(screen.getAllByRole('switch')[2], 'valueChange', true);
    expect(mockSave).toHaveBeenCalledWith({ channels: { announcement: { email: true } } });
    expect(screen.getAllByText("Can't be turned off").length).toBeGreaterThan(0);
  });
});
