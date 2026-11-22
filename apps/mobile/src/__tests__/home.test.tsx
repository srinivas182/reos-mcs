import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import { I18nextProvider } from 'react-i18next';

import HomeScreen from '@/app/index';
import { i18n } from '@/lib/i18n';
import { ToastProvider } from '@/ui';

const mockParams: { current: { capture?: string } } = { current: {} };
jest.mock('expo-router', () => ({
  router: { push: jest.fn(), setParams: jest.fn() },
  useLocalSearchParams: () => mockParams.current,
  useFocusEffect: jest.fn(),
}));
jest.mock('@/lib/todos', () => ({
  ...jest.requireActual('@/lib/todos'),
  useOpenTodos: () => ({ rows: [], reload: jest.fn() }),
}));
const mockUser: { current: object } = { current: { name: 'Sofia Reyes' } };
jest.mock('@/lib/auth', () => ({ useAuth: () => ({ user: mockUser.current }) }));
jest.mock('@/features/calendar/TodayAgenda', () => {
  const { Text } = jest.requireActual<typeof import('react-native')>('react-native');

  return { TodayAgenda: () => <Text>Today agenda</Text> };
});

// No connections by default; the connections tests cover the alert.
jest.mock('@/lib/connections', () => ({
  ...jest.requireActual('@/lib/connections'),
  useConnections: () => ({ connections: [], reload: jest.fn() }),
}));

const mockWhatsNew: { current: { item: { id: number; version: string; released_on: string; note: string } | null; dismiss: jest.Mock } } = {
  current: { item: null, dismiss: jest.fn() },
};
jest.mock('@/lib/whatsNew', () => ({ useWhatsNew: () => mockWhatsNew.current }));

jest.mock('@/lib/api', () => ({
  fetchHealth: jest.fn().mockResolvedValue({ status: 'ok', app: 'REOS', version: '0.1.0-dev', time: 'now' }),
}));

const renderHome = () =>
  render(
    <I18nextProvider i18n={i18n}>
      <ToastProvider>
        <HomeScreen />
      </ToastProvider>
    </I18nextProvider>,
  );

describe('HomeScreen', () => {
  afterEach(() => i18n.changeLanguage('en'));

  it('shows the API connection in English', async () => {
    await renderHome();
    await waitFor(() => expect(screen.getByText('Connected to REOS · version 0.1.0-dev')).toBeOnTheScreen());
    expect(screen.getByRole('header', { name: 'REOS' })).toBeOnTheScreen();
  });

  it('opens Me from the initials, top right', async () => {
    const { router } = jest.requireMock('expo-router') as { router: { push: jest.Mock } };
    await renderHome();
    await fireEvent.press(screen.getByRole('button', { name: 'Open Me' }));
    expect(router.push).toHaveBeenCalledWith('/me');
  });

  it("shows today's calendar to agents only", async () => {
    await renderHome();
    expect(screen.queryByText('Today agenda')).toBeNull();

    mockUser.current = {
      name: 'James Miller',
      active_role: { role: 'agent', organisation_id: 2 },
      roles: [{ role: 'agent', organisation: { id: 2, name: 'Lone Star Realty' } }],
    };
    await renderHome();
    expect(screen.getByText('Today agenda')).toBeOnTheScreen();
    mockUser.current = { name: 'Sofia Reyes' };
  });

  it('gives agents their to-dos and the Ask bar for voice to-dos; the shortcut opens capture listening', async () => {
    const { ExpoSpeechRecognitionModule } = jest.requireMock('expo-speech-recognition') as { ExpoSpeechRecognitionModule: { start: jest.Mock } };
    const { router } = jest.requireMock('expo-router') as { router: { setParams: jest.Mock } };
    mockUser.current = {
      name: 'James Miller',
      active_role: { role: 'agent', organisation_id: 2 },
      roles: [{ role: 'agent', organisation: { id: 2, name: 'Lone Star Realty' } }],
    };
    await renderHome();
    expect(screen.getByText('Your to-dos live here')).toBeOnTheScreen();

    await fireEvent.press(screen.getByRole('button', { name: 'Add a to-do: say it or type it' }));
    expect(screen.getByLabelText('What to do')).toBeOnTheScreen();
    await fireEvent.press(screen.getByRole('button', { name: 'Close' }));

    // reos://voice and the "Add a to-do" home-screen shortcut land on /?capture=voice.
    mockParams.current = { capture: 'voice' };
    await renderHome();
    await waitFor(() => expect(ExpoSpeechRecognitionModule.start).toHaveBeenCalled());
    await fireEvent.press(screen.getByRole('button', { name: 'Close' }));
    expect(router.setParams).toHaveBeenCalledWith({ capture: undefined });

    mockParams.current = {};
    mockUser.current = { name: 'Sofia Reyes' };
  });

  it('speaks Spanish when the phone does', async () => {
    await i18n.changeLanguage('es');
    await renderHome();
    await waitFor(() => expect(screen.getByText('Conectado a REOS · versión 0.1.0-dev')).toBeOnTheScreen());
  });

  it("shows What's new for the role once, and dismisses it", async () => {
    const dismiss = jest.fn();
    mockWhatsNew.current = { item: { id: 3, version: '6.2.0', released_on: '2026-11-22', note: 'Messages are here: talk to your broker and team in REOS.' }, dismiss };
    await renderHome();
    expect(screen.getByText("What's new in 6.2.0")).toBeOnTheScreen();
    expect(screen.getByText('Messages are here: talk to your broker and team in REOS.')).toBeOnTheScreen();
    await fireEvent.press(screen.getByRole('button', { name: "Dismiss what's new in 6.2.0" }));
    expect(dismiss).toHaveBeenCalled();
    mockWhatsNew.current = { item: null, dismiss: jest.fn() };
  });
});
