import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import { router, useLocalSearchParams } from 'expo-router';
import { I18nextProvider } from 'react-i18next';

import SendTasksScreen from '@/app/bookings/tasks/[uuid]';
import MenteeScreen from '@/app/mentoring/[agent]';
import MentorWorkspaceScreen from '@/app/mentoring/index';
import { i18n } from '@/lib/i18n';
import type { MenteeDetail, MentorToday } from '@/lib/mentoring';
import { ToastProvider } from '@/ui';

const mockApi = { GET: jest.fn(), POST: jest.fn(), PUT: jest.fn(), DELETE: jest.fn() };

jest.mock('expo-router', () => {
  const { useEffect } = jest.requireActual<typeof import('react')>('react');

  return {
    router: { push: jest.fn(), back: jest.fn(), replace: jest.fn() },
    useFocusEffect: (effect: () => void) => useEffect(effect, [effect]),
    useLocalSearchParams: jest.fn(() => ({})),
  };
});
jest.mock('@/lib/api', () => ({ api: () => mockApi }));

const ok = (data: unknown) => ({ data, error: undefined, response: { ok: true, status: 200 } });
const wrap = (ui: React.ReactElement) => (
  <I18nextProvider i18n={i18n}>
    <ToastProvider>{ui}</ToastProvider>
  </I18nextProvider>
);
const priya = { id: 21, name: 'Priya Shah', goal: 'launch', assigned: true, signal: 'quiet' as const, last_active: null, next_session: null };
const session = { uuid: 'b-1', agent: 'Tom Reyes', agent_id: 22, starts_at: '2026-11-24T17:00:00Z', minutes: 30, topic: 'Pricing my first listing', status: 'completed', session: null, summary: null, tasks_sent: false };

beforeEach(() => jest.clearAllMocks());

describe('The mentor workspace on the phone (A13.1b: screens 22–24 and 27)', () => {
  it('shows who needs the mentor and the wrap-ups to send', async () => {
    const today: MentorToday = { brokerage: 'Lone Star Realty', mentees: 2, sessions_this_week: 1, needs: [priya], upcoming: [], wrap_ups: [session] };
    mockApi.GET.mockImplementation(async (path: string) => (path === '/mentoring/today' ? ok({ data: today }) : ok({ data: [priya] })));
    await render(wrap(<MentorWorkspaceScreen />));

    expect(await screen.findByText('2 mentees · 1 session this week')).toBeTruthy();
    expect(screen.getByText('Quiet for a week · Assigned')).toBeTruthy();
    await fireEvent.press(screen.getByText('Wrap up: Tom Reyes'));
    expect(router.push).toHaveBeenCalledWith({ pathname: '/bookings/tasks/[uuid]', params: { uuid: 'b-1', name: 'Tom Reyes' } });
    await fireEvent.press(screen.getByText('Priya Shah'));
    expect(router.push).toHaveBeenCalledWith({ pathname: '/mentoring/[agent]', params: { agent: '21' } });
  });

  it('keeps private notes with Undo and adds a goal to the mentee\'s to-dos', async () => {
    jest.mocked(useLocalSearchParams).mockReturnValue({ agent: '21' });
    const detail: MenteeDetail = {
      mentee: priya,
      plan: { week: 2, weeks: 12, done: 3, total: 20 },
      scorecard: [{ key: 'conversations', done: 4, target: 10 }],
      fix: 'conversations',
      deals: { active: 1, under_contract: 0, closed: 0 },
      sessions: [],
      goals: [],
      notes: [{ id: 'n-1', body: 'Nervous about her first open house.', created_at: '2026-11-23T15:00:00Z' }],
    };
    mockApi.GET.mockResolvedValue(ok({ data: detail }));
    mockApi.POST.mockResolvedValue(ok({ data: {} }));
    mockApi.DELETE.mockResolvedValue(ok({ data: { removed: true } }));
    await render(wrap(<MenteeScreen />));

    expect(await screen.findByText('Week 2 of 12 · 3 of 20 done')).toBeTruthy();
    expect(screen.getByText('Conversations: 4 of 10')).toBeTruthy();
    await fireEvent.press(screen.getByLabelText('Note removed'));
    await waitFor(() => expect(mockApi.DELETE).toHaveBeenCalledWith('/mentoring/notes/{note}', { params: { path: { note: 'n-1' } } }));
    await fireEvent.press(await screen.findByText('Undo'));
    await waitFor(() => expect(mockApi.POST).toHaveBeenCalledWith('/mentoring/notes/{note}/restore', { params: { path: { note: 'n-1' } } }));

    await fireEvent.changeText(screen.getByLabelText('Goal'), 'Host an open house');
    await fireEvent.press(screen.getByText('Add a goal'));
    await waitFor(() => expect(mockApi.POST).toHaveBeenCalledWith('/mentoring/mentees/{agent}/goals', expect.objectContaining({ params: { path: { agent: 21 } }, body: expect.objectContaining({ title: 'Host an open house' }) })));
  });

  it('sends the wrap-up summary and dated action items', async () => {
    jest.mocked(useLocalSearchParams).mockReturnValue({ uuid: 'b-1', name: 'Priya Shah' });
    mockApi.GET.mockResolvedValue(ok({ data: { summary: 'We priced Willow Bend from three comps.', tasks: [{ title: 'Pull two more comps', due_date: '2026-11-30' }], engine: 'template' } }));
    mockApi.POST.mockResolvedValue(ok({ data: { sent: 1 } }));
    await render(wrap(<SendTasksScreen />));

    expect(await screen.findByDisplayValue('We priced Willow Bend from three comps.')).toBeTruthy();
    expect(screen.getByDisplayValue('Pull two more comps')).toBeTruthy();
    await fireEvent.press(screen.getByText('Send to to-dos'));
    await waitFor(() =>
      expect(mockApi.POST).toHaveBeenCalledWith('/bookings/{booking}/tasks', { params: { path: { booking: 'b-1' } }, body: { tasks: [{ title: 'Pull two more comps', due_date: '2026-11-30' }], summary: 'We priced Willow Bend from three comps.' } }),
    );
    expect(router.back).toHaveBeenCalled();
  });
});
