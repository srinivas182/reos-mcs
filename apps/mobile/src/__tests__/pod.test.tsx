import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import { router } from 'expo-router';
import { I18nextProvider } from 'react-i18next';

import PodScreen from '@/app/pod';
import { i18n } from '@/lib/i18n';
import type { Challenge, PodView } from '@/lib/pods';
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

const member: PodView = {
  status: 'member',
  waiting_since: null,
  pod: {
    id: 'pod-1',
    name: 'Launch pod 1',
    goal: 'launch',
    call: { day: 'mon', time: '08:30', timezone: 'America/Chicago', next: '2026-11-30T14:30:00Z', session: 'vs-1' },
    conversation: 'c-1',
    week_of: '2026-11-23',
    members: [
      { id: 1, name: 'Priya Shah', me: true, goal: null, metrics: [{ key: 'conversations', done: 3, target: 20 }], shares: ['conversations'] },
      { id: 2, name: 'Tom Reyes', me: false, goal: 'Two open houses', metrics: [], shares: [] },
    ],
  },
};
const none: PodView = { status: 'none', waiting_since: null, pod: null };
const challenge: Challenge = {
  id: 'ch-1', title: 'December conversations', metric: 'conversations', starts_on: '2026-12-01', ends_on: '2026-12-31', joined: false, open: true, entrants: 1,
  total: [{ id: 2, name: 'Tom Reyes', me: false, value: 12, improved: 4, personal_best: true }],
  improved: [{ id: 2, name: 'Tom Reyes', me: false, value: 12, improved: 4, personal_best: true }],
};

beforeEach(() => jest.clearAllMocks());

describe('Your pod on the phone (A13.2: screen 131)', () => {
  it('joins a pod and a challenge', async () => {
    mockApi.GET.mockImplementation(async (path: string) => (path === '/pod' ? ok({ data: none }) : ok({ data: [challenge] })));
    mockApi.POST.mockImplementation(async (path: string) => (path === '/pod/join' ? ok({ data: member }) : ok({ data: [{ ...challenge, joined: true, entrants: 2 }] })));
    await render(wrap(<PodScreen />));

    await fireEvent.press((await screen.findAllByLabelText('Join a pod')).at(-1)!);
    expect(await screen.findByText('Launch pod 1')).toBeTruthy();
    expect(screen.getByText('Two open houses')).toBeTruthy();
    await fireEvent.press(screen.getByLabelText('Join'));
    await waitFor(() => expect(mockApi.POST).toHaveBeenCalledWith('/challenges/{challenge}/join', { params: { path: { challenge: 'ch-1' } } }));
    expect(await screen.findByText('2 taking part', { exact: false })).toBeTruthy();
  });

  it('saves a goal, opens the call, and leaves with Undo', async () => {
    mockApi.GET.mockImplementation(async (path: string) => (path === '/pod' ? ok({ data: member }) : ok({ data: [] })));
    mockApi.PUT.mockResolvedValue(ok({ data: member }));
    mockApi.POST.mockImplementation(async (path: string) => (path === '/pod/leave' ? ok({ data: none, left: 'pod-1' }) : ok({ data: member })));
    await render(wrap(<PodScreen />));

    await fireEvent.changeText(await screen.findByLabelText('Your goal this week'), '20 conversations');
    await fireEvent.press(screen.getByLabelText('Save goal'));
    await waitFor(() => expect(mockApi.PUT).toHaveBeenCalledWith('/pod/goal', { body: { text: '20 conversations' } }));
    await fireEvent.press(screen.getByText('Join call'));
    expect(router.push).toHaveBeenCalledWith({ pathname: '/sessions/[id]', params: { id: 'vs-1' } });
    expect(screen.getByText('No challenges right now')).toBeTruthy();

    await fireEvent.press(screen.getByLabelText('Leave pod'));
    await fireEvent.press(await screen.findByText('Undo'));
    await waitFor(() => expect(mockApi.POST).toHaveBeenCalledWith('/pod/rejoin', { body: { pod: 'pod-1' } }));
  });
});
