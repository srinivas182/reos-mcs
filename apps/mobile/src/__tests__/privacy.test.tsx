import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import { I18nextProvider } from 'react-i18next';

import PrivacyScreen from '@/app/privacy';
import { i18n } from '@/lib/i18n';
import { ToastProvider } from '@/ui';

const summary = {
  switches: [
    { purpose: 'provider_network', on: true, chosen: false, at: null },
    { purpose: 'recruiting', on: false, chosen: false, at: null },
    { purpose: 'ai_personal_data', on: true, chosen: true, at: '2026-11-22T10:00:00Z' },
  ],
  records: [
    { id: 3, purpose: 'ai_personal_data', subject: null, granted: true, version: '2026-11-22', method: 'app', by_admin: false, at: '2026-11-22T10:00:00Z', current: true, withdrawable: true },
    { id: 1, purpose: 'terms', subject: null, granted: true, version: '2026-11-22', method: 'app', by_admin: false, at: '2026-11-20T10:00:00Z', current: true, withdrawable: false },
  ],
};

const mockChange = jest.fn().mockResolvedValue(true);
jest.mock('expo-router', () => ({ router: { push: jest.fn(), back: jest.fn() }, useFocusEffect: jest.fn() }));
jest.mock('@/lib/auth', () => ({ biometricAvailable: jest.fn().mockResolvedValue(false) }));
jest.mock('@/lib/consents', () => ({
  useConsents: () => ({ summary, failed: false, change: mockChange }),
  openLegal: jest.fn(),
}));

const renderScreen = () =>
  render(
    <I18nextProvider i18n={i18n}>
      <ToastProvider>
        <PrivacyScreen />
      </ToastProvider>
    </I18nextProvider>,
  );

describe('Me › Privacy (A6.3)', () => {
  beforeEach(() => mockChange.mockClear());

  it('shows the privacy switches with their defaults and your consents', async () => {
    await renderScreen();
    expect(screen.getByText('Provider network')).toBeOnTheScreen();
    expect(screen.getByText('Open to recruiting')).toBeOnTheScreen();
    expect(screen.getByText(/^your consents$/i)).toBeOnTheScreen();
    expect(screen.getAllByText('Terms of use')).toHaveLength(2);
    // The terms go only with the account: no Withdraw for them.
    expect(screen.getAllByRole('button', { name: /Withdraw consent/ })).toHaveLength(1);
  });

  it('turns a switch on, recorded from the app', async () => {
    await renderScreen();
    await fireEvent(screen.getAllByRole('switch')[1], 'valueChange', true);
    expect(mockChange).toHaveBeenCalledWith('recruiting', true, null);
  });

  it('withdraws a consent and offers Undo', async () => {
    await renderScreen();
    await fireEvent.press(screen.getByRole('button', { name: 'Withdraw consent: AI help with your own data' }));
    expect(mockChange).toHaveBeenCalledWith('ai_personal_data', false, null);
    await waitFor(() => expect(screen.getByText('AI help with your own data: consent withdrawn.')).toBeOnTheScreen());
  });

  it('opens the terms and privacy notice on reos.com', async () => {
    const { openLegal } = jest.requireMock('@/lib/consents') as { openLegal: jest.Mock };
    await renderScreen();
    await fireEvent.press(screen.getByRole('button', { name: 'Privacy notice' }));
    expect(openLegal).toHaveBeenCalledWith('privacy');
  });
});
