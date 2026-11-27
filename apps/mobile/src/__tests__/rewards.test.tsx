import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import { router } from 'expo-router';
import { I18nextProvider } from 'react-i18next';
import { Share } from 'react-native';

import GoalsScreen from '@/app/goals';
import RewardsScreen from '@/app/rewards';
import { i18n } from '@/lib/i18n';
import type { RewardsSummary, Today } from '@/lib/rewards';
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

const today: Today = {
  day: '2026-11-27',
  mode: 'launch',
  goals: [
    { key: 'conversations', done: 5, target: 5, points: 20, met: true, bonus: 0 },
    { key: 'post', done: 0, target: 1, points: 15, met: false, bonus: 0 },
  ],
  points_today: 20,
  bonus_today: 0,
  all_done: false,
  all_done_points: 25,
  streak: 4,
  best_streak: 9,
  balance: 3120,
  campaigns: [{ id: 'c1', title: 'Double points on CE', goal: 'ce_lesson', multiplier: 2, ends_on: '2026-11-30' }],
};
const rewards: RewardsSummary = {
  balance: 3120,
  value_cents: 3120,
  options: [{ price: 11, kind: 'credit_pack', name: '1,000 AI credits', points: 1500, credits: 1000, interval: 'once', affordable: true }],
  history: [{ reason: 'points_awarded', points: 20, memo: 'goal:2026-11-27:conversations', at: '2026-11-27T15:00:00Z' }],
};

beforeEach(() => jest.clearAllMocks());

describe('Goals and rewards on the phone (A13.3a: screens 111 and 112)', () => {
  it('shows today\'s goals and shares the streak', async () => {
    const share = jest.spyOn(Share, 'share').mockResolvedValue({ action: 'sharedAction' });
    mockApi.GET.mockResolvedValue(ok({ data: today }));
    await render(wrap(<GoalsScreen />));

    expect(await screen.findByText('4-day streak')).toBeTruthy();
    expect(screen.getByText('5 of 5 · 20 points')).toBeTruthy();
    expect(screen.getByText('Meet every goal for a 25-point bonus and keep your streak going.')).toBeTruthy();
    await fireEvent.press(screen.getByLabelText('Share my streak'));
    expect(share).toHaveBeenCalledWith({ message: "I'm on a 4-day streak of meeting my daily goals on REOS." });
    await fireEvent.press(screen.getByText('Your points'));
    expect(router.push).toHaveBeenCalledWith('/rewards');
  });

  it('uses points on AI credits and lists recent points', async () => {
    mockApi.GET.mockResolvedValue(ok({ data: rewards }));
    mockApi.POST.mockResolvedValue(ok({ data: { ...rewards, balance: 1620, value_cents: 1620 }, order: 'o-1' }));
    await render(wrap(<RewardsScreen />));

    expect(await screen.findByText('Daily goal: Conversations')).toBeTruthy();
    expect(screen.getByText('3,120 points · worth $31.20 inside REOS')).toBeTruthy();
    await fireEvent.press(screen.getByLabelText('Use 1500 points'));
    await waitFor(() => expect(mockApi.POST).toHaveBeenCalledWith('/rewards/redeem', { body: { price: 11 } }));
    expect(await screen.findByText('1,620 points · worth $16.20 inside REOS')).toBeTruthy();
  });
});
