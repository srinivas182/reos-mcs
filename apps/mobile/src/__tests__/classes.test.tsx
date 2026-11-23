import { fireEvent, render, screen } from '@testing-library/react-native';
import { router, useLocalSearchParams } from 'expo-router';
import { I18nextProvider } from 'react-i18next';

import ClassesScreen from '@/app/classes/index';
import { ClassPanel } from '@/features/classes/ClassPanel';
import { type ClassActions, type ClassLive, groupClasses, type LiveClassRow } from '@/lib/classes';
import { i18n } from '@/lib/i18n';
import { ToastProvider } from '@/ui';

const mockApi = { GET: jest.fn(), POST: jest.fn(), DELETE: jest.fn() };

jest.mock('expo-router', () => {
  const { useEffect } = jest.requireActual<typeof import('react')>('react');

  return {
    router: { push: jest.fn(), back: jest.fn(), replace: jest.fn() },
    useFocusEffect: (effect: () => void) => useEffect(effect, [effect]),
    useLocalSearchParams: jest.fn(() => ({})),
  };
});
jest.mock('@/lib/api', () => ({ api: () => mockApi }));
jest.mock('@/lib/auth', () => ({ useAuth: () => ({ user: null }) }));

const ok = (data: unknown) => ({ data, error: undefined, response: { ok: true, status: 200 } });
const wrap = (ui: React.ReactElement) => (
  <I18nextProvider i18n={i18n}>
    <ToastProvider>{ui}</ToastProvider>
  </I18nextProvider>
);
const soon = Date.now() + 2 * 3_600_000;
const row = (over: Partial<LiveClassRow> = {}): LiveClassRow => ({
  id: '6f9a1c2e-4b7d-4e1a-9c3f-2a8b5d6e7f01', title: 'Contract update: live review', agenda: null, course: { id: 12, title: 'Texas Contract Update' }, host: 'Nelle Hart',
  starts_at: new Date(soon).toISOString(), ends_at: new Date(soon + 7_200_000).toISOString(), minutes: 120, status: 'scheduled', ce: true, ce_hours: 2, checkin_minutes: 15,
  registered: 18, capacity: 30, me: null, can_register: true, full: false, can_join: false, ...over,
});
const live = (over: Partial<ClassLive> = {}): ClassLive => ({
  ce: true, viewers: 17, me: { role: 'guest', hand: false, on_stage: false }, hands: [],
  polls: [{ id: 3, question: 'Who initials the amendment?', options: [{ label: 'The buyer', votes: null }, { label: 'Both parties', votes: null }], total: null, my_vote: null, closed: false }],
  questions: [{ id: 41, body: 'Does the old form stay valid until January?', by: 'Ben Ortiz', likes: 6, liked: false, mine: false, answered: false }],
  messages: [], check: { id: 5, closes_at: new Date(soon).toISOString(), answered: false }, checks: [], next_check_at: null, ...over,
});
const actions = (): ClassActions => ({
  hand: jest.fn(), vote: jest.fn(), closePoll: jest.fn(), poll: jest.fn(), ask: jest.fn(), like: jest.fn(), answered: jest.fn(), chat: jest.fn(), stage: jest.fn(), sendCheck: jest.fn(), answerCheck: jest.fn(),
});

beforeEach(() => jest.clearAllMocks());

describe('Live classes on the phone (A10.2b: L12 and i11)', () => {
  it('lists a course\'s classes with CE and seats, and registers', async () => {
    jest.mocked(useLocalSearchParams).mockReturnValue({ course: '12' });
    mockApi.GET.mockResolvedValue(ok({ data: [row()], can_schedule: false, courses: [], lengths: [60] }));
    mockApi.POST.mockResolvedValue(ok({ data: row({ me: 'registered' }) }));
    await render(wrap(<ClassesScreen />));

    expect(await screen.findByText('Contract update: live review')).toBeTruthy();
    expect(mockApi.GET).toHaveBeenCalledWith('/classes', { params: { query: { course: 12 } } });
    expect(screen.getByText('2 CE hours')).toBeTruthy();
    expect(screen.getByText(/18 of 30 registered/)).toBeTruthy();
    await fireEvent.press(screen.getByRole('button', { name: 'Register' }));
    expect(mockApi.POST).toHaveBeenCalledWith('/classes/{session}/registration', { params: { path: { session: row().id } } });
  });

  it('joins a registered class that\'s open', async () => {
    jest.mocked(useLocalSearchParams).mockReturnValue({});
    mockApi.GET.mockResolvedValue(ok({ data: [row({ me: 'registered', status: 'live', can_join: true })], can_schedule: false, courses: [], lengths: [60] }));
    await render(wrap(<ClassesScreen />));

    await fireEvent.press(await screen.findByRole('button', { name: 'Join' }));
    expect(router.push).toHaveBeenCalledWith({ pathname: '/sessions/[id]', params: { id: row().id } });
  });

  it('groups by week', () => {
    const now = Date.now();
    const past = row({ id: 'p', starts_at: new Date(now - 3 * 86_400_000).toISOString(), ends_at: new Date(now - 3 * 86_400_000 + 3_600_000).toISOString() });
    const far = row({ id: 'f', starts_at: new Date(now + 40 * 86_400_000).toISOString(), ends_at: new Date(now + 40 * 86_400_000 + 3_600_000).toISOString() });
    expect(groupClasses([past, far], now).map((g) => g.group)).toEqual(['later', 'past']);
  });

  it('answers the attendance check, raises a hand, votes and likes in class', async () => {
    const a = actions();
    await render(wrap(<ClassPanel live={live()} actions={a} />));

    await fireEvent.press(screen.getByRole('button', { name: "I'm here" }));
    expect(a.answerCheck).toHaveBeenCalledWith(5);
    await fireEvent.press(screen.getByRole('button', { name: 'Raise hand' }));
    expect(a.hand).toHaveBeenCalledWith(true);
    await fireEvent.press(screen.getByRole('button', { name: 'Like (6 likes)' }));
    expect(a.like).toHaveBeenCalledWith(41, true);
    await fireEvent.press(screen.getByRole('radio', { name: 'Poll' }));
    await fireEvent.press(screen.getByRole('button', { name: 'Both parties' }));
    expect(a.vote).toHaveBeenCalledWith(3, 1);
  });

  it('lets the host bring a raised hand on and send a check', async () => {
    const a = actions();
    await render(wrap(<ClassPanel live={live({ me: { role: 'host', hand: false, on_stage: false }, check: null, hands: [{ id: 31, name: 'Ben Ortiz', since: null, on_stage: false }] })} actions={a} />));

    await fireEvent.press(screen.getByRole('radio', { name: 'Hands (1)' }));
    await fireEvent.press(screen.getByRole('button', { name: 'Bring on' }));
    expect(a.stage).toHaveBeenCalledWith(31, true);
    await fireEvent.press(screen.getByRole('radio', { name: 'Attendance' }));
    await fireEvent.press(screen.getByRole('button', { name: 'Send a check now' }));
    expect(a.sendCheck).toHaveBeenCalled();
  });
});
