import { act, fireEvent, render, screen } from '@testing-library/react-native';
import { I18nextProvider } from 'react-i18next';

import SessionScreen from '@/app/sessions/[id]';
import SessionsScreen from '@/app/sessions/index';
import { i18n } from '@/lib/i18n';
import { nextSession, type Session } from '@/lib/sessions';
import { ToastProvider } from '@/ui';

const mockGet = jest.fn();
const mockPost = jest.fn();
const mockPut = jest.fn();
jest.mock('@/lib/api', () => ({ api: () => ({ GET: mockGet, POST: mockPost, PUT: mockPut }) }));
jest.mock('@/lib/auth', () => ({ useAuth: () => ({ user: { id: 2, name: 'Sofia Reyes' } }) }));
jest.mock('expo-router', () => ({
  router: { push: jest.fn(), back: jest.fn() },
  useLocalSearchParams: () => ({ id: 's-1' }),
  useFocusEffect: (effect: () => void) => jest.requireActual<typeof import('react')>('react').useEffect(effect, [effect]),
}));

const session = (over: Partial<Session> = {}): Session => ({
  id: 's-1',
  kind: 'one_on_one',
  title: 'Contract review: TREC One to Four',
  agenda: 'Paragraphs 5 and 6',
  starts_at: new Date(Date.now() + 60_000).toISOString(),
  ends_at: new Date(Date.now() + 46 * 60_000).toISOString(),
  join_opens_at: new Date(Date.now() - 4 * 60_000).toISOString(),
  status: 'scheduled',
  host: { id: 7, name: 'Dr. Rachel Hughes' },
  people: [
    { id: 7, name: 'Dr. Rachel Hughes', role: 'host', joined: false },
    { id: 2, name: 'Sofia Reyes', role: 'guest', joined: false },
  ],
  me: 'guest',
  can_join: true,
  notes: null,
  notes_updated_at: null,
  notes_updated_by: null,
  ...over,
});

const wrap = (ui: React.ReactElement) =>
  render(
    <I18nextProvider i18n={i18n}>
      <ToastProvider>{ui}</ToastProvider>
    </I18nextProvider>,
  );

beforeEach(() => {
  mockGet.mockReset();
  mockPost.mockReset();
  mockPut.mockReset();
});

afterEach(() => jest.useRealTimers());

describe('Sessions on the phone (B6.3)', () => {
  it('lists coming sessions and picks the next one for Today', async () => {
    const list = { sessions: [session(), session({ id: 's-0', title: 'Getting started', status: 'ended' })], can_host: false, contacts: [], lengths: [] };
    mockGet.mockResolvedValue({ data: list });
    await wrap(<SessionsScreen />);

    expect(await screen.findByText('Contract review: TREC One to Four')).toBeOnTheScreen();
    expect(screen.getByText(/past sessions/i)).toBeOnTheScreen();
    expect(nextSession(list)?.id).toBe('s-1');
  });

  it('joins the room with a timer, and the guest can only leave', async () => {
    mockGet.mockResolvedValue({ data: session() });
    mockPost.mockResolvedValue({ data: { simulated: true, url: null, token: null, ends_at: new Date(Date.now() + 100_000).toISOString() } });
    await wrap(<SessionScreen />);

    await fireEvent.press(await screen.findByRole('button', { name: 'Join' }));
    expect(mockPost).toHaveBeenCalledWith('/sessions/{session}/join', { params: { path: { session: 's-1' } } });
    expect(await screen.findByLabelText(/left$/)).toBeOnTheScreen();
    expect(screen.getByText(/Two minutes left/)).toBeOnTheScreen();
    expect(screen.getByText('Sofia Reyes (you)')).toBeOnTheScreen();
    expect(screen.queryByRole('button', { name: 'End for everyone' })).toBeNull();
    await fireEvent.press(screen.getByRole('button', { name: 'Leave' }));
    expect(screen.queryByText('Sofia Reyes (you)')).toBeNull();
  });

  it('lets the host end it for everyone, and closes at the booked end', async () => {
    jest.useFakeTimers({ doNotFake: ['nextTick'] });
    mockGet.mockResolvedValue({ data: session({ me: 'host' }) });
    mockPost.mockImplementation((path: string) =>
      Promise.resolve(
        path.endsWith('/join')
          ? { data: { simulated: true, url: null, token: null, ends_at: new Date(Date.now() + 2_000).toISOString() } }
          : { data: session({ me: 'host', status: 'ended', can_join: false }) },
      ),
    );
    await wrap(<SessionScreen />);

    await fireEvent.press(await screen.findByRole('button', { name: 'Join' }));
    expect(await screen.findByRole('button', { name: 'End for everyone' })).toBeOnTheScreen();

    await act(async () => {
      jest.advanceTimersByTime(3_000);
    });
    expect(screen.queryByRole('button', { name: 'End for everyone' })).toBeNull();
  });

  it('saves the shared notes', async () => {
    mockGet.mockResolvedValue({ data: session({ status: 'ended', can_join: false }) });
    mockPut.mockResolvedValue({ data: session({ status: 'ended', notes: 'Read section 4.', notes_updated_by: 'Sofia Reyes', notes_updated_at: '2026-11-24T21:46:00Z' }) });
    await wrap(<SessionScreen />);

    expect(await screen.findByText('The session has ended.')).toBeOnTheScreen();
    await fireEvent.changeText(screen.getByLabelText('Session notes'), 'Read section 4.');
    await fireEvent.press(screen.getByRole('button', { name: 'Save notes' }));
    expect(mockPut).toHaveBeenCalledWith('/sessions/{session}/notes', { params: { path: { session: 's-1' } }, body: { notes: 'Read section 4.' } });
    expect(await screen.findByText(/Last edited by Sofia Reyes/)).toBeOnTheScreen();
  });
});
