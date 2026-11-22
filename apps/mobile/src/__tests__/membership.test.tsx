import { fireEvent, render, screen } from '@testing-library/react-native';
import { router } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { Platform } from 'react-native';
import { I18nextProvider } from 'react-i18next';

import MembershipScreen from '@/app/membership';
import { i18n } from '@/lib/i18n';
import { ToastProvider } from '@/ui';

const mockApi = { GET: jest.fn(), POST: jest.fn() };
const mockAuth = { status: 'signedIn', user: { email: 'sofia@example.com' } };

jest.mock('expo-router', () => {
  const { useEffect } = jest.requireActual<typeof import('react')>('react');

  return { router: { push: jest.fn(), back: jest.fn(), replace: jest.fn() }, useFocusEffect: (effect: () => void) => useEffect(effect, [effect]) };
});
jest.mock('@/lib/api', () => ({ api: () => mockApi }));
jest.mock('@/lib/auth', () => ({ useAuth: () => mockAuth }));

const ok = (data: unknown, status = 200) => ({ data, error: undefined, response: { ok: status < 400, status } });
const membership = (overrides = {}) => ({ active: false, features: [], includes: ['180 hours of qualifying courses', 'Pass Promise'], ios_buy_link: 'email', buy_url: null, ...overrides });
const wrap = (ui: React.ReactElement) =>
  render(
    <I18nextProvider i18n={i18n}>
      <ToastProvider>{ui}</ToastProvider>
    </I18nextProvider>,
  );

const setOs = (os: 'ios' | 'android') => Object.defineProperty(Platform, 'OS', { get: () => os, configurable: true });

beforeEach(() => {
  jest.clearAllMocks();
  setOs('ios');
});

describe('Membership (candidate screen 6)', () => {
  it('shows what membership includes, no price, and emails the link on iPhone', async () => {
    mockApi.GET.mockResolvedValue(ok(membership()));
    mockApi.POST.mockResolvedValue(ok({ sent: true }));

    await wrap(<MembershipScreen />);

    expect(await screen.findByText('180 hours of qualifying courses')).toBeTruthy();
    expect(screen.queryByText(/\$/)).toBeNull();
    expect(screen.queryByRole('button', { name: 'Continue on reos.com' })).toBeNull();

    await fireEvent.press(screen.getByRole('button', { name: 'Email me the link' }));
    expect(mockApi.POST).toHaveBeenCalledWith('/me/membership/email-link');
    expect(await screen.findByText('Sent to sofia@example.com. Open it on any device to choose a plan.')).toBeTruthy();
  });

  it('links to reos.com when REOS switches it on', async () => {
    mockApi.GET.mockResolvedValue(ok(membership({ ios_buy_link: 'link', buy_url: 'https://reos.com/settings/billing' })));

    await wrap(<MembershipScreen />);
    await fireEvent.press(await screen.findByRole('button', { name: 'Continue on reos.com' }));

    expect(WebBrowser.openBrowserAsync).toHaveBeenCalledWith('https://reos.com/settings/billing');
  });

  it('shows the same words without a link on Android', async () => {
    setOs('android');
    mockApi.GET.mockResolvedValue(ok(membership({ ios_buy_link: 'link', buy_url: 'https://reos.com/settings/billing' })));

    await wrap(<MembershipScreen />);

    expect(await screen.findByText(/Plans are chosen on reos.com in any browser/)).toBeTruthy();
    expect(screen.queryByRole('button', { name: 'Continue on reos.com' })).toBeNull();
    expect(screen.queryByRole('button', { name: 'Email me the link' })).toBeNull();
  });

  it('unlocks a sponsored seat with a code', async () => {
    mockApi.GET.mockResolvedValueOnce(ok(membership())).mockResolvedValue(ok(membership({ active: true })));
    mockApi.POST.mockResolvedValue(ok({ sponsor: 'Texas Realty School', product: 'Texas Pre-licence Complete', features: ['learn.qualifying'] }));

    await wrap(<MembershipScreen />);
    await fireEvent.press(await screen.findByRole('button', { name: 'Enter a code' }));
    await fireEvent.changeText(screen.getByLabelText('Code'), 'tex-7k4m2q');
    await fireEvent.press(screen.getByRole('button', { name: 'Use code' }));

    expect(mockApi.POST).toHaveBeenCalledWith('/me/codes', { body: { code: 'tex-7k4m2q' } });
    expect(await screen.findByText('Texas Pre-licence Complete from Texas Realty School is unlocked.')).toBeTruthy();
    await fireEvent.press(screen.getByRole('button', { name: 'Start learning' }));
    expect(router.replace).toHaveBeenCalledWith('/learn');
  });

  it('says why a code did not work', async () => {
    mockApi.GET.mockResolvedValue(ok(membership()));
    mockApi.POST.mockResolvedValue({ data: undefined, error: { errors: { code: ['That code has expired.'] } }, response: { ok: false, status: 422 } });

    await wrap(<MembershipScreen />);
    await fireEvent.press(await screen.findByRole('button', { name: 'Enter a code' }));
    await fireEvent.changeText(screen.getByLabelText('Code'), 'TEX-OLD123');
    await fireEvent.press(screen.getByRole('button', { name: 'Use code' }));

    expect(await screen.findByText('That code has expired.')).toBeTruthy();
  });
});
