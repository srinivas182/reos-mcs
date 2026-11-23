import { fireEvent, render, screen } from '@testing-library/react-native';
import { router, useLocalSearchParams } from 'expo-router';
import { I18nextProvider } from 'react-i18next';
import { Share } from 'react-native';

import ProgressScreen from '@/app/practice/progress';
import ResultsScreen from '@/app/practice/results/[id]';
import { i18n } from '@/lib/i18n';
import type { Results, WeeklyProgress } from '@/lib/practice';
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

const week: WeeklyProgress = {
  report: {
    week_start: '2026-11-16',
    week_end: '2026-11-22',
    first_name: 'Sofia',
    minutes: 70,
    goal_minutes: 140,
    per_day: [25, 0, 20, 0, 15, 10, 0],
    questions: 42,
    cards: 18,
    readiness: { now: 68, before: 64, change: 4 },
    lessons: 3,
    hours: 1.2,
    weakest: 'finance',
    streak: 4,
  },
  shares: { school: false, can_share_school: true, link: null },
};

beforeEach(() => jest.clearAllMocks());

describe('weekly progress (screen 55, A9.2e)', () => {
  it('shows the week against the plan, goes back a week, and shares with the school or a link', async () => {
    const share = jest.spyOn(Share, 'share').mockResolvedValue({ action: 'sharedAction', activityType: undefined });
    mockApi.GET.mockImplementation((_path: string, init?: { params?: { query?: { week?: string } } }) =>
      Promise.resolve(ok({ data: init?.params?.query?.week ? { ...week, report: { ...week.report, week_start: init.params.query.week, minutes: 30 } } : week })),
    );
    mockApi.POST.mockResolvedValueOnce(ok({ data: { ...week.shares, school: true } })).mockResolvedValueOnce(ok({ data: { ...week.shares, school: true, link: 'https://reos.com/reports/1b6e' } }));

    await render(wrap(<ProgressScreen />));

    expect(await screen.findByText('70 minutes')).toBeTruthy();
    expect(screen.getByText('68% (+4 this week)')).toBeTruthy();
    expect(screen.getByText('Finance')).toBeTruthy();
    expect(screen.getByLabelText(/: 25 min$/)).toBeTruthy();
    expect(screen.queryByRole('button', { name: 'Next week' })).toBeNull();

    await fireEvent.press(screen.getByRole('button', { name: 'Share with my school' }));
    expect(mockApi.POST).toHaveBeenCalledWith('/practice/reports/weekly/share', { body: { with: 'school', week: '2026-11-16' } });
    expect(await screen.findByText('Shared with your school')).toBeTruthy();

    await fireEvent.press(screen.getByRole('button', { name: 'Make a link for a study buddy' }));
    expect(share).toHaveBeenCalledWith({ message: 'https://reos.com/reports/1b6e' });
    expect(await screen.findByRole('button', { name: 'Copy the link' })).toBeTruthy();

    await fireEvent.press(screen.getByRole('button', { name: 'Previous week' }));
    expect(await screen.findByText('30 minutes')).toBeTruthy();
    expect(mockApi.GET).toHaveBeenLastCalledWith('/practice/reports/weekly', { params: { query: { week: '2026-11-09' } } });
    expect(screen.getByRole('button', { name: 'Next week' })).toBeTruthy();
  });
});

describe('Ask why (screen 45, A9.2e)', () => {
  const results: Results = {
    id: 9,
    kind: 'topic',
    mode: 'exam',
    passed: null,
    portions: [],
    timing: null,
    score: 0,
    correct: 0,
    total: 1,
    pass_line: 70,
    previous: null,
    seconds: 60,
    topics: [{ topic: 'finance', correct: 0, total: 1, percent: 0, weight: 7 }],
    weakest: ['finance'],
    strongest: [],
    review: [{ id: 4, n: 1, topic: 'finance', prompt: 'When must PMI end?', options: ['At 78% LTV', 'Never'], choice: 1, right: 0, correct: false, explanation: 'At 78% LTV.', source: 'Exam outline', portion: 'national', scored: true, lesson: null, math: null }],
  };

  it('opens the lesson behind a missed question with the question asked, or says none covers it', async () => {
    jest.mocked(useLocalSearchParams).mockReturnValue({ id: '9' });
    let why: unknown = { lesson_id: 31, seconds: 245, question: 'Why?' };
    mockApi.GET.mockImplementation((path: string) => Promise.resolve(ok({ data: path.includes('/why/') ? why : results })));

    await render(wrap(<ResultsScreen />));
    await fireEvent.press(await screen.findByRole('button', { name: 'Ask why' }));
    expect(router.push).toHaveBeenCalledWith({ pathname: '/learn/[id]', params: { id: '31', ask: 'Why?', t: '245' } });

    why = null;
    await fireEvent.press(screen.getByRole('button', { name: 'Ask why' }));
    expect(await screen.findByText('No lesson you can open covers this one yet.')).toBeTruthy();
  });
});
