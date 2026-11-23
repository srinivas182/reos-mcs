import { fireEvent, render, screen } from '@testing-library/react-native';
import { router } from 'expo-router';
import { I18nextProvider } from 'react-i18next';

import PlanScreen from '@/app/agent/plan';
import { AgentToday } from '@/features/agent/AgentToday';
import type { AgentPlan, AgentToday as Today, Scorecard } from '@/lib/agent';
import { i18n } from '@/lib/i18n';
import { setDone } from '@/lib/todos';
import { ToastProvider } from '@/ui';

const mockApi = { GET: jest.fn(), PUT: jest.fn(), POST: jest.fn() };

jest.mock('expo-router', () => {
  const { useEffect } = jest.requireActual<typeof import('react')>('react');

  return {
    router: { push: jest.fn(), back: jest.fn(), replace: jest.fn() },
    useFocusEffect: (effect: () => void) => useEffect(effect, [effect]),
  };
});
jest.mock('@/lib/api', () => ({ api: () => mockApi }));
jest.mock('@/lib/todos', () => ({ setDone: jest.fn(async () => 'saved') }));

const ok = (data: unknown) => ({ data, error: undefined, response: { ok: true, status: 200 } });
const wrap = (ui: React.ReactElement) => (
  <I18nextProvider i18n={i18n}>
    <ToastProvider>{ui}</ToastProvider>
  </I18nextProvider>
);

const today = (over: Partial<Today> = {}): Today => ({
  goal: 'launch',
  stats: [
    { key: 'week', value: 3, target: 13 },
    { key: 'conversations', value: 12, target: 20 },
    { key: 'appointments', value: 1, target: 2 },
  ],
  items: [
    { key: 'todo-1', kind: 'todo', reason: 'overdue', title: 'Call Maria back', detail: 'Maria Lopez', source: 'Follow Up Boss', at: '2026-11-22T06:00:00Z', all_day: true, todo_id: 1, deal: null, plan: null },
    { key: 'event-7', kind: 'event', reason: 'appointment', title: 'Showing with the Nguyens', detail: null, source: 'Google Calendar', at: '2026-11-24T21:30:00Z', all_day: false, todo_id: null, deal: null, plan: null },
    { key: 'plan-call_sphere-3', kind: 'plan', reason: 'plan', title: 'Call 10 people you know', detail: null, source: '90-day plan', at: null, all_day: true, todo_id: null, deal: null, plan: { code: 'call_sphere', week: 3 } },
  ],
  more: 0,
  day_one: null,
  plan_week: 3,
  ...over,
});

const plan = (over: Partial<AgentPlan> = {}): AgentPlan => ({
  goal: 'launch',
  starts_on: '2026-11-10',
  ends_on: '2027-02-09',
  week: 3,
  weeks: 13,
  phases: [
    { code: 'setup', from: 1, to: 2, current: false },
    { code: 'conversations', from: 3, to: 6, current: true },
  ],
  targets: { conversations: 20, appointments: 2, clients: 1, ce_hours: 1 },
  tasks: [{ code: 'call_sphere', week: 3, done: false, auto: false, repeats: true, link: 'contacts' }],
  done: 5,
  total: 52,
  promised: false,
  ...over,
});

const card: Scorecard = {
  week_of: '2026-11-23',
  metrics: [
    { key: 'conversations', done: 6, target: 20 },
    { key: 'appointments', done: 1, target: 2 },
  ],
  fix: 'conversations',
};

beforeEach(() => jest.clearAllMocks());

describe('Today and the 90-day plan on the phone (A11.1b: screens 10, 14, 15, 110)', () => {
  it('ranks every tool in one list with its source and marks a to-do done at the source', async () => {
    await render(wrap(<AgentToday today={today()} onChanged={jest.fn()} />));

    expect(screen.getByText('Overdue')).toBeTruthy();
    expect(screen.getByText(/From Follow Up Boss/)).toBeTruthy();
    expect(screen.getByText('12 of 20')).toBeTruthy();
    await fireEvent.press(screen.getByLabelText('Mark done: Call Maria back'));
    expect(setDone).toHaveBeenCalledWith({ id: 1, clientId: null }, true);
    expect(screen.queryByText('Call Maria back')).toBeNull();

    mockApi.PUT.mockResolvedValue(ok({ data: plan() }));
    await fireEvent.press(screen.getByLabelText('Mark done: Call 10 people you know'));
    expect(mockApi.PUT).toHaveBeenCalledWith('/agent/plan/tasks/{code}', { params: { path: { code: 'call_sphere' } }, body: { week: 3, done: true } });
    expect(screen.queryByLabelText('Mark done: Showing with the Nguyens')).toBeNull();
  });

  it('shows day one until there are contacts or a connected tool', async () => {
    await render(
      wrap(
        <AgentToday
          today={today({
            items: [],
            day_one: [
              { code: 'import_contacts', minutes: 5, done: false, link: 'contacts' },
              { code: 'connect_tools', minutes: 3, done: false, link: 'connections' },
            ],
          })}
          onChanged={jest.fn()}
        />,
      ),
    );

    expect(screen.getByText('Your first day')).toBeTruthy();
    await fireEvent.press(screen.getByText('Connect your CRM, calendar and email'));
    expect(router.push).toHaveBeenCalledWith('/connections');
  });

  it('ticks plan tasks, makes the promise and shows the scorecard', async () => {
    mockApi.GET.mockImplementation(async (path: string) => ok({ data: path === '/agent/scorecard' ? card : plan() }));
    mockApi.PUT.mockResolvedValue(ok({ data: plan({ done: 6, tasks: [{ code: 'call_sphere', week: 3, done: true, auto: false, repeats: true, link: 'contacts' }] }) }));
    mockApi.POST.mockResolvedValue(ok({ data: plan({ promised: true }) }));
    await render(wrap(<PlanScreen />));

    expect(await screen.findByText('Week 3 of 13')).toBeTruthy();
    expect(await screen.findByText(/Conversations are behind/)).toBeTruthy();
    await fireEvent.press(screen.getByLabelText('Done: Call 10 people you know'));
    expect(await screen.findByText('6 of 52 tasks done')).toBeTruthy();
    await fireEvent.press(screen.getByText('I’m in'));
    expect(await screen.findByText(/Promise made/)).toBeTruthy();
    expect(mockApi.POST).toHaveBeenCalledWith('/agent/plan/promise');
  });
});
