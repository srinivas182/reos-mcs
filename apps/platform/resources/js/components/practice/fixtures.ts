import type {
    Attempt,
    Coach,
    HubTest,
    MathKind,
    MathProblem,
    ReadinessMap,
    Results,
    StudyToday,
    TopicPlan,
} from './types';

export const studyToday: StudyToday = {
    readiness: 72,
    topics: {
        agency: 100,
        contracts: 89,
        ownership: 88,
        finance: 43,
        math: 29,
        state_rules: 90,
    },
    weakest: ['math', 'finance'],
    exam_on: '2027-03-15',
    days_to_exam: 112,
    streak: 3,
    minutes_per_day: 20,
    reminder_time: '19:30',
    diagnostic_done: true,
    continue: {
        lesson_id: 7,
        title: '6.2 Agency disclosure',
        course: 'Principles of Real Estate II',
        position: 245,
    },
    tasks: [
        {
            id: 1,
            kind: 'practice',
            why: 'weak',
            minutes: 10,
            done: false,
            day: '2026-11-23',
            lesson: null,
            topic: 'math',
        },
        {
            id: 2,
            kind: 'lesson',
            why: 'next',
            minutes: 10,
            done: true,
            day: '2026-11-23',
            lesson: {
                id: 7,
                title: '6.2 Agency disclosure',
                course: 'Principles of Real Estate II',
            },
            topic: null,
        },
    ],
    behind_minutes: 0,
    plan: { id: 4, reason: 'diagnostic', built_at: '2026-11-23T15:00:00Z' },
};

export const hub: HubTest[] = [
    {
        kind: 'diagnostic',
        topic: null,
        questions: 50,
        minutes: 45,
        best: 72,
        last: 72,
        open: null,
        last_attempt: 9,
    },
    ...(
        [
            'agency',
            'contracts',
            'ownership',
            'finance',
            'math',
            'state_rules',
        ] as const
    ).map((topic): HubTest => ({
        kind: 'topic',
        topic,
        questions: 10,
        minutes: null,
        best: topic === 'math' ? 60 : null,
        last: topic === 'math' ? 60 : null,
        open: null,
        last_attempt: null,
    })),
];

export const attempt: Attempt = {
    id: 9,
    kind: 'diagnostic',
    mode: 'exam',
    topic: null,
    total: 3,
    answered: 1,
    started_at: '2026-11-23T15:00:00Z',
    ends_at: '2026-11-23T15:45:00Z',
    seconds_left: 2700,
    submitted: false,
    paused: false,
    section: null,
    questions: [
        {
            id: 101,
            n: 1,
            topic: 'agency',
            prompt: 'A listing agreement usually creates which kind of agency?',
            options: [
                'Universal agency',
                'General agency',
                'Special agency',
                'Dual agency',
            ],
            choice: 2,
            flagged: true,
            feedback: null,
        },
        {
            id: 102,
            n: 2,
            topic: 'math',
            prompt: 'A $240,000 loan on a home valued at $300,000 has what loan-to-value ratio?',
            options: ['75%', '80%', '85%', '125%'],
            choice: null,
            flagged: false,
            feedback: null,
        },
        {
            id: 103,
            n: 3,
            topic: 'state_rules',
            prompt: 'Who licenses and regulates real estate license holders in Texas?',
            options: [
                'The Texas Association of Realtors',
                'The Texas Real Estate Commission (TREC)',
                'The county clerk',
                'The Texas Department of Insurance',
            ],
            choice: null,
            flagged: false,
            feedback: null,
        },
    ],
};

export const results: Results = {
    id: 9,
    kind: 'diagnostic',
    mode: 'exam',
    score: 72,
    correct: 36,
    total: 50,
    pass_line: 70,
    passed: null,
    portions: [],
    timing: null,
    previous: null,
    seconds: 2412,
    topics: [
        { topic: 'math', correct: 2, total: 7, percent: 29, weight: 7 },
        { topic: 'finance', correct: 3, total: 7, percent: 43, weight: 7 },
        { topic: 'ownership', correct: 7, total: 8, percent: 88, weight: 8 },
        { topic: 'agency', correct: 9, total: 9, percent: 100, weight: 9 },
    ],
    weakest: ['math', 'finance'],
    strongest: ['agency', 'ownership'],
    review: [
        {
            id: 102,
            n: 2,
            topic: 'math',
            prompt: 'A $240,000 loan on a home valued at $300,000 has what loan-to-value ratio?',
            options: ['75%', '80%', '85%', '125%'],
            choice: 0,
            right: 1,
            correct: false,
            explanation: '$240,000 ÷ $300,000 = 0.80, or 80%.',
            source: 'Licensing exam content outline · Real estate calculations: loan-to-value',
            portion: 'national',
            scored: true,
            lesson: null,
        },
    ],
};

/** A full simulation, National passed and Texas not yet (A9.1). */
export const simulationResults: Results = {
    ...results,
    id: 12,
    kind: 'simulation',
    score: 71,
    correct: 85,
    total: 120,
    pass_line: 70,
    passed: false,
    portions: [
        {
            portion: 'national',
            correct: 61,
            total: 80,
            pass: 56,
            passed: true,
            percent: 76,
            previous: 68,
            seconds: 7920,
            pace: 106,
        },
        {
            portion: 'state',
            correct: 24,
            total: 40,
            pass: 28,
            passed: false,
            percent: 60,
            previous: null,
            seconds: 4980,
            pace: 108,
        },
    ],
    timing: { seconds_per_question: 95, pace: 107 },
};

/** Sofia six weeks in (A9.2): National nearly there, Texas at the line, one simulation passed. */
export const readinessMap: ReadinessMap = {
    percent: 84,
    portions: { national: 84, state: 93 },
    line: 90,
    booking: {
        ready: false,
        national: false,
        state: true,
        simulations: 1,
        needed: 2,
        url: 'https://www.pearsonvue.com/us/en/tx/realestate.html',
    },
    topics: [
        {
            topic: 'finance',
            portion: 'national',
            weight: 7,
            percent: 61,
            answers: 48,
            shown: true,
            trend: 9,
            weak: true,
        },
        {
            topic: 'math',
            portion: 'national',
            weight: 7,
            percent: 72,
            answers: 41,
            shown: true,
            trend: -3,
            weak: false,
        },
        {
            topic: 'contracts',
            portion: 'national',
            weight: 9,
            percent: 80,
            answers: 63,
            shown: true,
            trend: 0,
            weak: false,
        },
        {
            topic: 'ownership',
            portion: 'national',
            weight: 8,
            percent: 84,
            answers: 52,
            shown: true,
            trend: 4,
            weak: false,
        },
        {
            topic: 'state_rules',
            portion: 'state',
            weight: 10,
            percent: 88,
            answers: 90,
            shown: true,
            trend: 6,
            weak: false,
        },
        {
            topic: 'agency',
            portion: 'national',
            weight: 9,
            percent: 90,
            answers: 8,
            shown: false,
            trend: null,
            weak: false,
        },
    ],
    gain: { topic: 'finance', portion: 'national', points: 7 },
    computed_on: '2027-01-04',
};

/** The practice hub's ranking for Sofia with a fix plan under way (A9.2b). */
export const coach: Coach = {
    drill: {
        topics: ['finance', 'math', 'state_rules'],
        questions: 15,
        misses: 4,
        open: null,
    },
    plans: [{ id: 31, topic: 'finance', done: 2, steps: 5 }],
    ranked: [
        { mode: 'plan', why: 'carry_on', topic: 'finance', plan_id: 31 },
        { mode: 'drill', why: 'weakest', topic: null, plan_id: null },
        { mode: 'simulation', why: 'check', topic: null, plan_id: null },
        { mode: 'readiness', why: 'map', topic: null, plan_id: null },
    ],
};

/** Sofia's plan for finance, two steps in (A9.2b). */
export const topicPlan: TopicPlan = {
    id: 31,
    topic: 'finance',
    now: 58,
    start: 43,
    goal: 70,
    weak: true,
    minutes: 28,
    completed: false,
    steps: [
        {
            n: 1,
            kind: 'lesson',
            minutes: 5,
            status: 'done',
            lesson: { id: 7, title: 'Mortgage insurance', seconds: 185 },
            attempt_id: null,
            results_id: null,
        },
        {
            n: 2,
            kind: 'review',
            minutes: 4,
            status: 'done',
            lesson: null,
            attempt_id: null,
            results_id: null,
        },
        {
            n: 3,
            kind: 'practice',
            minutes: 10,
            status: 'current',
            lesson: null,
            attempt_id: null,
            results_id: null,
        },
        {
            n: 4,
            kind: 'practice',
            minutes: 10,
            status: 'todo',
            lesson: null,
            attempt_id: null,
            results_id: null,
        },
        {
            n: 5,
            kind: 'retest',
            minutes: 8,
            status: 'todo',
            lesson: null,
            attempt_id: null,
            results_id: null,
        },
    ],
    misses: [
        {
            id: 205,
            prompt: 'Under the Homeowners Protection Act, borrower-paid PMI must end automatically when the loan balance is scheduled to reach',
            options: [
                '90 percent of the original value',
                '50 percent of the original value',
                '78 percent of the original value',
                '100 percent of the original value',
            ],
            right: 2,
            explanation:
                'PMI ends automatically at a scheduled 78 percent loan-to-value; the borrower may ask to cancel at 80 percent.',
            source: 'Licensing exam content outline · Financing: mortgage insurance',
        },
    ],
};

/** A smart drill two answers in, both right (A9.2b). */
export const drillAttempt: Attempt = {
    ...attempt,
    id: 14,
    kind: 'drill',
    mode: 'practice',
    ends_at: null,
    seconds_left: null,
    questions: attempt.questions.map((q, i) =>
        i < 2
            ? {
                  ...q,
                  choice: i === 0 ? 2 : 1,
                  flagged: false,
                  feedback: {
                      correct: true,
                      right: i === 0 ? 2 : 1,
                      explanation: 'Because the rule says so.',
                      source: 'Licensing exam content outline',
                  },
              }
            : { ...q, choice: null, flagged: false, feedback: null },
    ),
};

/** The math lab's menu (A9.2c). */
export const mathKinds: { kind: MathKind; solved: number }[] = [
    { kind: 'proration', solved: 3 },
    { kind: 'commission', solved: 5 },
    { kind: 'ltv', solved: 1 },
    { kind: 'area', solved: 0 },
    { kind: 'cap', solved: 0 },
    { kind: 'net', solved: 2 },
    { kind: 'points', solved: 0 },
];

/** A loan-to-value problem as the server sends it: no answers. */
export const mathProblem: MathProblem = {
    kind: 'ltv',
    seed: 4711,
    prompt: 'A buyer agrees to pay $325,000. The home appraises at $318,000. The lender will lend 95% of the value. What are the loan and the down payment?',
    steps: [
        { n: 1, label: 'The value the lender uses', unit: 'dollars' },
        { n: 2, label: 'The most the lender will lend', unit: 'dollars' },
        { n: 3, label: "The buyer's down payment", unit: 'dollars' },
    ],
};
