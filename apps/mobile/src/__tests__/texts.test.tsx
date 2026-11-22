import { fireEvent, render, screen } from '@testing-library/react-native';
import { I18nextProvider } from 'react-i18next';

import NotificationSettingsScreen from '@/app/notification-settings';
import { i18n } from '@/lib/i18n';
import { ToastProvider } from '@/ui';

const mockSendCode = jest.fn().mockResolvedValue({ ok: true });
const mockVerify = jest.fn().mockResolvedValue({ ok: true });
const mockTurn = jest.fn().mockResolvedValue({ ok: true });
const mockTexts: { current: { phone: string | null; on: boolean; available: boolean } } = { current: { phone: null, on: false, available: true } };

jest.mock('expo-router', () => ({ router: { push: jest.fn(), back: jest.fn() }, useFocusEffect: jest.fn() }));
jest.mock('@/lib/notifications', () => ({
  deviceTimezone: () => 'America/Chicago',
  useNotificationSettings: () => ({
    settings: {
      kinds: [{ kind: 'session_starting', group: 'learning', locked: false, urgent: true, digestible: false, channels: { inbox: true, push: true, email: true, sms: false }, digest: null }],
      channels: ['inbox', 'push', 'email', 'sms'],
      phones: 1,
      texts: mockTexts.current,
      quiet: { enabled: true, start: '21:00', end: '07:00' },
      timezone: 'America/Chicago',
    },
    failed: false,
    save: jest.fn(),
    sendCode: mockSendCode,
    verifyNumber: mockVerify,
    turnTexts: mockTurn,
  }),
}));

const wrap = (ui: React.ReactElement) =>
  render(
    <I18nextProvider i18n={i18n}>
      <ToastProvider>{ui}</ToastProvider>
    </I18nextProvider>,
  );

describe('Texts on the phone (B6.2c)', () => {
  it('verifies a US number with a code', async () => {
    mockTexts.current = { phone: null, on: false, available: true };
    await wrap(<NotificationSettingsScreen />);

    await fireEvent.changeText(screen.getByLabelText('Mobile number'), '+15125550100');
    await fireEvent.press(screen.getByRole('button', { name: 'Send code' }));
    expect(mockSendCode).toHaveBeenCalledWith('+15125550100');

    await fireEvent.changeText(await screen.findByLabelText('Code'), '123456');
    await fireEvent.press(screen.getByRole('button', { name: 'Verify number' }));
    expect(mockVerify).toHaveBeenCalledWith('+15125550100', '123456');
  });

  it('turns texts on with the consent words, then lets kinds go by text', async () => {
    mockTexts.current = { phone: '••• ••• 0100', on: false, available: true };
    const { rerender } = await wrap(<NotificationSettingsScreen />);
    expect(screen.getByText('Texts go to ••• ••• 0100.')).toBeOnTheScreen();
    expect(screen.getByRole('switch', { name: 'Session starting by Text' })).toBeDisabled();

    await fireEvent(screen.getByRole('switch', { name: 'Get texts from REOS' }), 'valueChange', true);
    expect(mockTurn).toHaveBeenCalledWith(true);

    mockTexts.current = { phone: '••• ••• 0100', on: true, available: true };
    await rerender(
      <I18nextProvider i18n={i18n}>
        <ToastProvider>
          <NotificationSettingsScreen />
        </ToastProvider>
      </I18nextProvider>,
    );
    expect(screen.getByRole('switch', { name: 'Session starting by Text' })).toBeEnabled();
  });
});
