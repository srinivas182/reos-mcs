import { fireEvent, render, screen } from '@testing-library/react-native';
import { router, useLocalSearchParams } from 'expo-router';
import { I18nextProvider } from 'react-i18next';

import TestScreen from '@/app/practice/[id]';
import PracticeHub from '@/app/practice/index';
import ResultsScreen from '@/app/practice/results/[id]';
import { CandidateToday } from '@/features/study/CandidateToday';
import { i18n } from '@/lib/i18n';
import type { Attempt, CandidateToday as Today, Results } from '@/lib/practice';
import { countdown } from '@/lib/practice';
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

const today: Today = {
  readiness: 72,
  topics: { agency: 100, contracts: 100, ownership: 100, finance: 0, math: 0, state_rules: 100 },
  weakest: ['finance', 'math'],
  exam_on: '2027-03-15',
  days_to_exam: 112,
  streak: 3,
  minutes_per_day: 20,
  reminder_time: '19:30',
  diagnostic_done: true,
  continue: { lesson_id: 7, title: 'Agency basics', course: 'Principles of Real Estate I', position: 245 },
  tasks: [
    { id: 1, kind: 'practice', why: 'weak', minutes: 10, done: false, day: '2026-11-23', lesson: null, topic: 'finance' },
    { id: 2, kind: 'lesson', why: 'next', minutes: 10, done: true, day: '2026-11-23', lesson: { id: 7, title: 'Agency basics', course: 'Principles of Real Estate I' }, topic: null },
  ],
  behind_minutes: 0,
  plan: { id: 4, reason: 'diagnostic', built_at: '2026-11-23T15:00:00Z' },
};

const question = (id: number, n: number, extra: Partial<Attempt['questions'][number]> = {}): Attempt['questions'][number] => ({
  id,
  n,
  topic: 'agency',
  prompt: `Question ${n}?`,
  options: ['Option A', 'Option B', 'Option C', 'Option D'],
  choice: null,
  flagged: false,
  feedback: null,
  ...extra,
});

const attempt: Attempt = {
  id: 9,
  kind: 'diagnostic',
  mode: 'exam',
  topic: null,
  total: 2,
  answered: 0,
  started_at: '2026-11-23T15:00:00Z',
  ends_at: '2026-11-23T15:45:00Z',
  seconds_left: 2700,
  submitted: false,
  paused: false,
  section: null,
  questions: [question(101, 1), question(102, 2, { topic: 'math' })],
};

const results: Results = {
  id: 9,
  kind: 'diagnostic',
  mode: 'exam',
  passed: null,
  portions: [],
  timing: null,
  score: 50,
  correct: 1,
  total: 2,
  pass_line: 70,
  previous: null,
  seconds: 600,
  topics: [
    { topic: 'math', correct: 0, total: 1, percent: 0, weight: 7 },
    { topic: 'agency', correct: 1, total: 1, percent: 100, weight: 9 },
  ],
  weakest: ['math'],
  strongest: ['agency'],
  review: [
    { id: 101, n: 1, topic: 'agency', prompt: 'Question 1?', options: ['Option A', 'Option B', 'Option C', 'Option D'], choice: 0, right: 0, correct: true, explanation: 'Because A.', source: 'TREC Rules', portion: 'national', scored: true, lesson: null },
    { id: 102, n: 2, topic: 'math', prompt: 'Question 2?', options: ['Option A', 'Option B', 'Option C', 'Option D'], choice: null, right: 2, correct: false, explanation: 'Because C.', source: 'Exam outline', portion: 'national', scored: true, lesson: null },
  ],
};

beforeEach(() => jest.clearAllMocks());

describe('exam simulations (A9.1)', () => {
  it('starts a portion test from the intro sheet in the chosen mode, then runs portion by portion with the calculator', async () => {
    const sim: Attempt = { ...attempt, id: 31, kind: 'simulation', mode: 'practice', total: 2, section: { index: 1, of: 2, portion: 'national', minutes: 150, questions: 2 }, seconds_left: 9000 };
    mockApi.GET.mockResolvedValue(ok({ data: [{ kind: 'simulation', topic: null, questions: 135, minutes: 240, best: null, last: null, open: null, last_attempt: null }] }));
    mockApi.POST.mockResolvedValue(ok({ data: sim }));
    await render(wrap(<PracticeHub />));

    await fireEvent.press(await screen.findByText('Full simulation'));
    await fireEvent.press(screen.getByText('Practice mode'));
    await fireEvent.press(screen.getByRole('button', { name: 'Start test' }));
    expect(mockApi.POST).toHaveBeenCalledWith('/practice/tests', { body: { kind: 'simulation', topic: null, mode: 'practice' } });
    expect(router.push).toHaveBeenCalledWith({ pathname: '/practice/[id]', params: { id: '31' } });

    jest.mocked(useLocalSearchParams).mockReturnValue({ id: '31' });
    mockApi.GET.mockResolvedValue(ok({ data: sim }));
    mockApi.POST.mockResolvedValue(ok({ data: { ...sim, section: { index: 2, of: 2, portion: 'state', minutes: 90, questions: 2 }, seconds_left: 5400 } }));
    await render(wrap(<TestScreen />));

    expect(await screen.findByText('National portion, part 1 of 2 · 2 questions · why after each answer')).toBeTruthy();
    await fireEvent.press(screen.getByRole('button', { name: 'Calculator' }));
    expect(screen.getByLabelText('Percent')).toBeTruthy();
    await fireEvent.press(screen.getByText('Next'));
    await fireEvent.press(screen.getByText('Review and submit'));
    await fireEvent.press(screen.getByText('Finish and start the next portion'));
    expect(mockApi.POST).toHaveBeenCalledWith('/practice/attempts/{attempt}/finish', { params: { path: { attempt: 31 } } });
    expect(await screen.findByText('Texas portion, part 2 of 2 · 2 questions · why after each answer')).toBeTruthy();
  });
});

describe('Today for candidates (screen 12)', () => {
  it('shows readiness, days to the exam, the streak, Continue at its second, and today\'s tasks with why', async () => {
    mockApi.POST.mockResolvedValue(ok({ data: { ...attempt, kind: 'topic', mode: 'practice', id: 11 } }));

    await render(wrap(<CandidateToday today={today} />));

    expect(screen.getByLabelText('Readiness 72%')).toBeTruthy();
    expect(screen.getByText('112 days to your exam')).toBeTruthy();
    expect(screen.getByLabelText('3-day streak')).toBeTruthy();
    expect(screen.getByText('Finance is your fastest win.')).toBeTruthy();
    expect(screen.getByText("Today's plan · 20 minutes")).toBeTruthy();
    expect(screen.getByText('10 min · Next in your course · Done')).toBeTruthy();

    await fireEvent.press(screen.getByLabelText('Continue: Agency basics'));
    expect(router.push).toHaveBeenCalledWith({ pathname: '/learn/[id]', params: { id: '7', t: '245' } });

    await fireEvent.press(screen.getByText('Finance quiz'));
    expect(mockApi.POST).toHaveBeenCalledWith('/practice/tests', { body: { kind: 'topic', topic: 'finance' } });
    expect(router.push).toHaveBeenCalledWith({ pathname: '/practice/[id]', params: { id: '11' } });
  });
});

describe('tests (screens 39–43)', () => {
  it('lists the diagnostic and topic quizzes', async () => {
    mockApi.GET.mockResolvedValue(ok({ data: [{ kind: 'diagnostic', topic: null, questions: 50, minutes: 45, best: null, last: null, open: null, last_attempt: null }, { kind: 'topic', topic: 'math', questions: 10, minutes: null, best: 80, last: 80, open: null, last_attempt: 3 }] }));

    await render(wrap(<PracticeHub />));

    expect(await screen.findByText(/50 questions across the Texas exam outline in 45 minutes/)).toBeTruthy();
    expect(screen.getByText('10 questions · Best 80%')).toBeTruthy();
  });

  it('saves every tap, flags, warns about blanks, and submits to the results', async () => {
    jest.mocked(useLocalSearchParams).mockReturnValue({ id: '9' });
    mockApi.GET.mockResolvedValue(ok({ data: attempt }));
    mockApi.PUT.mockImplementation(async (_path: string, { body }: { body: { choice: number | null; flagged: boolean } }) => ok({ data: { ...question(101, 1), ...body } }));
    mockApi.POST.mockResolvedValue(ok({ data: results }));

    await render(wrap(<TestScreen />));

    expect(await screen.findByText('Question 1?')).toBeTruthy();
    expect(screen.getByLabelText('45:00 left')).toBeTruthy();
    await fireEvent.press(screen.getByLabelText('Option B'));
    expect(mockApi.PUT).toHaveBeenCalledWith('/practice/attempts/{attempt}/answers/{question}', { params: { path: { attempt: 9, question: 101 } }, body: { choice: 1, flagged: false } });
    await fireEvent.press(screen.getByRole('button', { name: 'Flag for review' }));
    expect(mockApi.PUT).toHaveBeenLastCalledWith('/practice/attempts/{attempt}/answers/{question}', { params: { path: { attempt: 9, question: 101 } }, body: { choice: 1, flagged: true } });

    await fireEvent.press(screen.getByRole('button', { name: 'Next' }));
    expect(screen.getByText('Question 2?')).toBeTruthy();
    await fireEvent.press(screen.getByRole('button', { name: 'Review and submit' }));
    expect(screen.getByText('1 question is not answered.')).toBeTruthy();
    expect(screen.getByLabelText('Question 1, answered, flagged')).toBeTruthy();
    await fireEvent.press(screen.getByRole('button', { name: 'Submit anyway' }));

    expect(mockApi.POST).toHaveBeenCalledWith('/practice/attempts/{attempt}/submit', { params: { path: { attempt: 9 } } });
    expect(router.replace).toHaveBeenCalledWith({ pathname: '/practice/results/[id]', params: { id: '9' } });
  });

  it('shows the report: score against the pass line, topics weakest first, every answer with why', async () => {
    jest.mocked(useLocalSearchParams).mockReturnValue({ id: '9' });
    mockApi.GET.mockResolvedValue(ok({ data: results }));

    await render(wrap(<ResultsScreen />));

    expect(await screen.findByText('50%')).toBeTruthy();
    expect(screen.getByText('Below the 70% pass line')).toBeTruthy();
    expect(screen.getByText('Real estate math · 0%')).toBeTruthy();
    expect(screen.getByText('Next, your plan spends extra time on Real estate math.')).toBeTruthy();
    expect(screen.getByText('Right answer: Option C')).toBeTruthy();
    expect(screen.getByText('Source: Exam outline')).toBeTruthy();
    expect(countdown(3729)).toBe('1:02:09');
  });
});
