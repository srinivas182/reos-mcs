import { fireEvent, render, screen } from '@testing-library/react-native';
import { router, useLocalSearchParams } from 'expo-router';
import { I18nextProvider } from 'react-i18next';
import { Share } from 'react-native';

import ChallengeScreen from '@/app/challenges/[uuid]';
import ChallengesScreen from '@/app/practice/challenges';
import { i18n } from '@/lib/i18n';
import type { ChallengeBoard } from '@/lib/practice';
import { ToastProvider } from '@/ui';

const mockApi = { GET: jest.fn(), POST: jest.fn(), PUT: jest.fn() };

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
const board: ChallengeBoard = {
  uuid: '6c1f0f3e-58a4-4a63-9a8e-2d7f1c0b9e21',
  link: 'https://reos.com/challenges/6c1f0f3e-58a4-4a63-9a8e-2d7f1c0b9e21',
  from: 'Sofia',
  mine: true,
  questions: 10,
  expires_at: '2026-11-30T15:00:00Z',
  expired: false,
  attempt_id: 71,
  submitted: true,
  entries: [
    { first_name: 'Marco', correct: 9, total: 10, hints: 1, points: 85, mine: false, finished: true },
    { first_name: 'Sofia', correct: 8, total: 10, hints: 0, points: 80, mine: true, finished: true },
  ],
};

beforeEach(() => jest.clearAllMocks());

describe('challenges (screen 52, A9.3b)', () => {
  it('shows each board by points, first names only, and challenges a friend through the share sheet', async () => {
    const share = jest.spyOn(Share, 'share').mockResolvedValue({ action: 'sharedAction', activityType: undefined });
    mockApi.GET.mockResolvedValue(ok({ data: [board] }));
    mockApi.POST.mockResolvedValue(ok({ data: { ...board, uuid: 'b2', link: 'https://reos.com/challenges/b2', attempt_id: 72, submitted: false, entries: [] } }));
    await render(wrap(<ChallengesScreen />));

    expect(await screen.findByText('85 points · 9 of 10 right')).toBeTruthy();
    expect(screen.getByText('Sofia (you)')).toBeTruthy();

    await fireEvent.press(screen.getByRole('button', { name: 'Challenge a friend' }));
    expect(mockApi.POST).toHaveBeenCalledWith('/practice/challenges');
    expect(share).toHaveBeenCalledWith({ message: 'I challenge you to 10 Texas exam questions on REOS: https://reos.com/challenges/b2' });
    expect(router.push).toHaveBeenCalledWith({ pathname: '/practice/[id]', params: { id: '72' } });
  });

  it('accepts a challenge from its link and starts the same questions', async () => {
    jest.mocked(useLocalSearchParams).mockReturnValue({ uuid: board.uuid });
    mockApi.GET.mockResolvedValue(ok({ data: { ...board, mine: false, attempt_id: null, submitted: false, entries: [] } }));
    mockApi.POST.mockResolvedValue(ok({ data: { ...board, mine: false, attempt_id: 73, submitted: false } }));
    await render(wrap(<ChallengeScreen />));

    expect(await screen.findByText('Sofia sent you 10 questions. Answer them and see who scores more.')).toBeTruthy();
    await fireEvent.press(screen.getByRole('button', { name: 'Accept' }));
    expect(mockApi.POST).toHaveBeenCalledWith('/practice/challenges/{challenge}/accept', { params: { path: { challenge: board.uuid } } });
    expect(router.replace).toHaveBeenCalledWith({ pathname: '/practice/[id]', params: { id: '73' } });
  });
});
