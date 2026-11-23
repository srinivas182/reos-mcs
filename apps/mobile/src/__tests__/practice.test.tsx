import { fireEvent, render, screen } from '@testing-library/react-native';
import { router, useLocalSearchParams } from 'expo-router';
import { I18nextProvider } from 'react-i18next';

import TestScreen from '@/app/practice/[id]';
import PracticeHub from '@/app/practice/index';
import ReadinessScreen from '@/app/practice/readiness';
import ResultsScreen from '@/app/practice/results/[id]';
import FixScreen from '@/app/practice/fix/[id]';
import MathLabScreen from '@/app/practice/math';
import GoodAtScreen from '@/app/practice/topics';
import { CandidateToday } from '@/features/study/CandidateToday';
import { i18n } from '@/lib/i18n';
import type { Attempt, Coach, ReadinessMap, CandidateToday as Today, Results, TopicPlan } from '@/lib/practice';
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
  portions: { national: 72, state: 95 },
  ready_to_book: false,
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
    { id: 101, n: 1, topic: 'agency', prompt: 'Question 1?', options: ['Option A', 'Option B', 'Option C', 'Option D'], choice: 0, right: 0, correct: true, explanation: 'Because A.', source: 'TREC Rules', portion: 'national', scored: true, lesson: null, math: null },
    { id: 102, n: 2, topic: 'math', prompt: 'Question 2?', options: ['Option A', 'Option B', 'Option C', 'Option D'], choice: null, right: 2, correct: false, explanation: 'Because C.', source: 'Exam outline', portion: 'national', scored: true, lesson: null, math: null },
  ],
};

beforeEach(() => jest.clearAllMocks());

const coach: Coach = {
  drill: { topics: ['finance', 'math', 'state_rules'], questions: 15, misses: 4, open: null },
  plans: [{ id: 31, topic: 'finance', done: 2, steps: 5 }],
  ranked: [
    { mode: 'plan', why: 'carry_on', topic: 'finance', plan_id: 31 },
    { mode: 'drill', why: 'weakest', topic: null, plan_id: null },
    { mode: 'readiness', why: 'map', topic: null, plan_id: null },
  ],
};
/** The hub loads its tests and its ranking. */
const hubGets = (tests: unknown) => (path: string) => Promise.resolve(ok({ data: path === '/practice/hub' ? coach : tests }));

describe('exam simulations (A9.1)', () => {
  it('starts a portion test from the intro sheet in the chosen mode, then runs portion by portion with the calculator', async () => {
    const sim: Attempt = { ...attempt, id: 31, kind: 'simulation', mode: 'practice', total: 2, section: { index: 1, of: 2, portion: 'national', minutes: 150, questions: 2 }, seconds_left: 9000 };
    mockApi.GET.mockImplementation(hubGets([{ kind: 'simulation', topic: null, questions: 135, minutes: 240, best: null, last: null, open: null, last_attempt: null }]));
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

    expect(screen.getByLabelText('Readiness 72%: predicted pass chance of your weaker portion')).toBeTruthy();
    expect(screen.getByText('112 days to your exam')).toBeTruthy();
    expect(screen.getByLabelText('3-day streak')).toBeTruthy();
    expect(screen.getByText('Finance is your fastest win.')).toBeTruthy();
    expect(screen.getByText("Today's plan · 20 minutes")).toBeTruthy();
    expect(screen.getByText('10 min · Next in your course · Done')).toBeTruthy();

    // The ring opens the Readiness map (screen 54).
    await fireEvent.press(screen.getByHintText('Opens your readiness map'));
    expect(router.push).toHaveBeenCalledWith('/practice/readiness');

    await fireEvent.press(screen.getByLabelText('Continue: Agency basics'));
    expect(router.push).toHaveBeenCalledWith({ pathname: '/learn/[id]', params: { id: '7', t: '245' } });

    await fireEvent.press(screen.getByText('Finance quiz'));
    expect(mockApi.POST).toHaveBeenCalledWith('/practice/tests', { body: { kind: 'topic', topic: 'finance' } });
    expect(router.push).toHaveBeenCalledWith({ pathname: '/practice/[id]', params: { id: '11' } });
  });
});

describe('tests (screens 39–43)', () => {
  it('lists the diagnostic and topic quizzes', async () => {
    mockApi.GET.mockImplementation(hubGets([{ kind: 'diagnostic', topic: null, questions: 50, minutes: 45, best: null, last: null, open: null, last_attempt: null }, { kind: 'topic', topic: 'math', questions: 10, minutes: null, best: 80, last: 80, open: null, last_attempt: 3 }]));

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

const readinessMap: ReadinessMap = {
  percent: 84,
  portions: { national: 84, state: 93 },
  line: 90,
  booking: { ready: false, national: false, state: true, simulations: 1, needed: 2, url: 'https://www.pearsonvue.com/us/en/tx/realestate.html' },
  topics: [
    { topic: 'finance', portion: 'national', weight: 7, percent: 61, answers: 48, shown: true, trend: 9, weak: true },
    { topic: 'math', portion: 'national', weight: 7, percent: 72, answers: 41, shown: true, trend: -3, weak: false },
    { topic: 'state_rules', portion: 'state', weight: 10, percent: 88, answers: 90, shown: true, trend: 6, weak: false },
    { topic: 'agency', portion: 'national', weight: 9, percent: 90, answers: 8, shown: false, trend: null, weak: false },
  ],
  gain: { topic: 'finance', portion: 'national', points: 7 },
  computed_on: '2027-01-04',
};

describe('readiness (screens 54 and 44, A9.2)', () => {
  it('shows each portion against the booking line and what booking still needs, and practises a topic', async () => {
    mockApi.GET.mockResolvedValue(ok({ data: readinessMap }));
    mockApi.POST.mockResolvedValue(ok({ data: { ...attempt, kind: 'topic', mode: 'practice', id: 12 } }));
    await render(wrap(<ReadinessScreen />));

    expect(await screen.findByLabelText('National portion: 84% predicted pass chance')).toBeTruthy();
    expect(screen.getByText('6 points to the booking line')).toBeTruthy();
    expect(screen.getByLabelText('1 of 2 recent simulations passed: Not yet')).toBeTruthy();
    expect(screen.getByText('8 of 10 answers so far')).toBeTruthy();

    await fireEvent.press(screen.getByText('Finance'));
    expect(mockApi.POST).toHaveBeenCalledWith('/practice/tests', { body: { kind: 'drill', topic: 'finance' } });
    expect(router.push).toHaveBeenCalledWith({ pathname: '/practice/[id]', params: { id: '12' } });
  });

  it('offers booking with the testing vendor once ready', async () => {
    mockApi.GET.mockResolvedValue(ok({ data: { ...readinessMap, portions: { national: 92, state: 95 }, booking: { ...readinessMap.booking, ready: true, national: true, simulations: 2 } } }));
    await render(wrap(<ReadinessScreen />));

    expect(await screen.findByText('Ready to book')).toBeTruthy();
    expect(screen.getByLabelText('Book the exam')).toBeTruthy();
  });

  it('filters topics by portion, with trends, and fixes the weakest', async () => {
    mockApi.GET.mockResolvedValue(ok({ data: readinessMap }));
    mockApi.POST.mockResolvedValue(ok({ data: { ...attempt, kind: 'topic', mode: 'practice', id: 13 } }));
    await render(wrap(<GoodAtScreen />));

    expect(await screen.findByText('Up 9 points this week')).toBeTruthy();
    expect(screen.getByText('Fixing Finance could add 7 points to your pass chance on the National portion.')).toBeTruthy();

    await fireEvent.press(screen.getByLabelText('Texas'));
    expect(screen.queryByText('Up 9 points this week')).toBeNull();
    expect(screen.getByText('Up 6 points this week')).toBeTruthy();

    mockApi.POST.mockResolvedValue(ok({ data: { id: 31 } }));
    await fireEvent.press(screen.getByLabelText('Fix my weakest'));
    expect(mockApi.POST).toHaveBeenCalledWith('/practice/topic-plans', { body: { topic: 'finance' } });
    expect(router.push).toHaveBeenCalledWith({ pathname: '/practice/fix/[id]', params: { id: '31' } });
  });
});

const plan: TopicPlan = {
  id: 31,
  topic: 'finance',
  now: 58,
  start: 43,
  goal: 70,
  weak: true,
  minutes: 28,
  completed: false,
  steps: [
    { n: 1, kind: 'lesson', minutes: 5, status: 'done', lesson: { id: 7, title: 'Mortgage insurance', seconds: 185 }, attempt_id: null, results_id: null },
    { n: 2, kind: 'practice', minutes: 10, status: 'current', lesson: null, attempt_id: null, results_id: null },
    { n: 3, kind: 'practice', minutes: 10, status: 'todo', lesson: null, attempt_id: null, results_id: null },
    { n: 4, kind: 'retest', minutes: 8, status: 'todo', lesson: null, attempt_id: null, results_id: null },
  ],
  misses: [{ id: 205, prompt: 'When must borrower-paid PMI end automatically?', options: ['90%', '50%', '78%', '100%'], right: 2, explanation: 'At a scheduled 78 percent loan-to-value.', source: 'Licensing exam content outline' }],
};

describe('drills and fix plans (screens 38, 46 and 47, A9.2b)', () => {
  it('ranks the hub, starts the smart drill and carries on with a plan', async () => {
    mockApi.GET.mockImplementation(hubGets([]));
    mockApi.POST.mockResolvedValue(ok({ data: { ...attempt, id: 14, kind: 'drill', mode: 'practice' } }));
    await render(wrap(<PracticeHub />));

    expect(await screen.findByText('Today: Finance, Real estate math, and Texas rules')).toBeTruthy();
    expect(screen.getByText('Brings back 4 questions you missed')).toBeTruthy();
    await fireEvent.press(screen.getByLabelText('Start drill'));
    expect(mockApi.POST).toHaveBeenCalledWith('/practice/tests', { body: { kind: 'drill', topic: null } });
    expect(router.push).toHaveBeenCalledWith({ pathname: '/practice/[id]', params: { id: '14' } });

    await fireEvent.press(screen.getByText('Carry on: Finance (2 of 5 done)'));
    expect(router.push).toHaveBeenCalledWith({ pathname: '/practice/fix/[id]', params: { id: '31' } });
  });

  it('keeps the drill streak and strikes out an option with Hint', async () => {
    const drill: Attempt = {
      ...attempt,
      id: 14,
      kind: 'drill',
      mode: 'practice',
      ends_at: null,
      seconds_left: null,
      questions: [question(101, 1, { choice: 1, feedback: { correct: true, right: 1, explanation: 'Why.', source: 'Outline' } }), question(102, 2)],
    };
    (useLocalSearchParams as jest.Mock).mockReturnValue({ id: '14' });
    mockApi.GET.mockResolvedValue(ok({ data: drill }));
    mockApi.POST.mockResolvedValue(ok({ data: { eliminated: 3 } }));
    await render(wrap(<TestScreen />));

    expect(await screen.findByText('Smart drill')).toBeTruthy();
    expect(screen.getByLabelText('Streak: 1 right in a row')).toBeTruthy();
    await fireEvent.press(screen.getByLabelText('Hint'));
    expect(mockApi.POST).toHaveBeenCalledWith('/practice/attempts/{attempt}/questions/{question}/hint', { params: { path: { attempt: 14, question: 102 } } });
    expect(await screen.findByText('Not this one: Option D')).toBeTruthy();
    expect(screen.getByLabelText('Option D. ruled out by the hint')).toBeTruthy();
  });

  it('shows the fix plan against its goal and continues to the next step\'s test', async () => {
    (useLocalSearchParams as jest.Mock).mockReturnValue({ id: '31' });
    mockApi.GET.mockResolvedValue(ok({ data: plan }));
    mockApi.POST.mockResolvedValue(ok({ data: { kind: 'practice', lesson: null, attempt_id: 44 } }));
    await render(wrap(<FixScreen />));

    expect(await screen.findByText('Now: 58%')).toBeTruthy();
    expect(screen.getByText('28 minutes left')).toBeTruthy();
    await fireEvent.press(screen.getByText('Watch: Mortgage insurance'));
    expect(router.push).toHaveBeenCalledWith({ pathname: '/learn/[id]', params: { id: '7', t: '185' } });

    await fireEvent.press(screen.getByLabelText('Continue'));
    expect(mockApi.POST).toHaveBeenCalledWith('/practice/topic-plans/{plan}/continue', { params: { path: { plan: 31 } } });
    expect(router.push).toHaveBeenCalledWith({ pathname: '/practice/[id]', params: { id: '44' } });
  });
});

describe('math lab (screen 48, A9.2c)', () => {
  const problem = {
    kind: 'ltv',
    seed: 4711,
    prompt: 'A buyer agrees to pay $325,000. The home appraises at $318,000. The lender will lend 95% of the value.',
    steps: [
      { n: 1, label: 'The value the lender uses', unit: 'dollars' },
      { n: 2, label: 'The most the lender will lend', unit: 'dollars' },
    ],
  };

  it('opens a missed question\'s kind, checks each step with a hint when wrong, and offers a variant', async () => {
    (useLocalSearchParams as jest.Mock).mockReturnValue({ kind: 'ltv' });
    mockApi.GET.mockResolvedValue(ok({ data: [{ kind: 'ltv', solved: 1 }] }));
    mockApi.POST.mockImplementation((path: string, init: { body: Record<string, unknown> }) => {
      if (path === '/practice/math/problems') return Promise.resolve(ok({ data: problem }));
      const { step, value, reveal } = init.body;
      if (step === 1 && value === 325000) return Promise.resolve(ok({ data: { correct: false, hint: 'Lenders use the lower of the price and the appraisal.', answer: null, solved: false } }));
      if (step === 1) return Promise.resolve(ok({ data: { correct: true, hint: null, answer: '$318,000', solved: false } }));

      return Promise.resolve(ok({ data: reveal ? { correct: false, hint: null, answer: '$302,100', solved: true } : { correct: false, hint: 'Multiply that value by the loan-to-value ratio.', answer: null, solved: false } }));
    });
    await render(wrap(<MathLabScreen />));

    expect(await screen.findByText(problem.prompt)).toBeTruthy();
    await fireEvent.changeText(screen.getByLabelText('The value the lender uses'), '$325,000');
    await fireEvent.press(screen.getByLabelText('Check step'));
    expect(mockApi.POST).toHaveBeenLastCalledWith('/practice/math/check', { body: { kind: 'ltv', seed: 4711, step: 1, value: 325000 } });
    expect(await screen.findByText('Lenders use the lower of the price and the appraisal.')).toBeTruthy();

    await fireEvent.changeText(screen.getByLabelText('The value the lender uses'), '318000');
    await fireEvent.press(screen.getByLabelText('Check step'));
    expect(await screen.findByText('Right: $318,000')).toBeTruthy();

    await fireEvent.changeText(screen.getByLabelText('The most the lender will lend'), '1');
    await fireEvent.press(screen.getByLabelText('Check step'));
    await fireEvent.press(await screen.findByLabelText('Show the answer'));
    expect(await screen.findByText('The answer: $302,100')).toBeTruthy();
    expect(screen.getByText('Solved')).toBeTruthy();

    await fireEvent.press(screen.getByLabelText('New numbers, same kind'));
    expect(mockApi.POST).toHaveBeenLastCalledWith('/practice/math/problems', { body: { kind: 'ltv' } });
  });
});

