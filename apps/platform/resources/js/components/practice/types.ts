/** Candidate practice and the study plan (A8.3, ADR 0032). */
export type Topic =
    | 'agency'
    | 'contracts'
    | 'ownership'
    | 'finance'
    | 'math'
    | 'state_rules';

export type Feedback = {
    correct: boolean;
    right: number;
    explanation: string;
    source: string;
};

export type TestQuestion = {
    id: number;
    n: number;
    topic: Topic;
    prompt: string;
    options: string[];
    choice: number | null;
    flagged: boolean;
    feedback: Feedback | null;
};

export type TestKind =
    | 'diagnostic'
    | 'topic'
    | 'national'
    | 'state'
    | 'simulation'
    | 'drill'
    | 'retest'
    | 'challenge';

export type Portion = 'national' | 'state';

export type Attempt = {
    id: number;
    kind: TestKind;
    mode: 'exam' | 'practice';
    topic: Topic | null;
    /** A challenge's share id (A9.3b). */
    challenge: string | null;
    total: number;
    answered: number;
    started_at: string;
    ends_at: string | null;
    seconds_left: number | null;
    submitted: boolean;
    paused: boolean;
    /** The portion being taken (A9.1): its number, of how many, and its format. */
    section: {
        index: number;
        of: number;
        portion: Portion;
        minutes: number;
        questions: number;
    } | null;
    questions: TestQuestion[];
};

export type TopicScore = {
    topic: Topic;
    correct: number;
    total: number;
    percent: number;
    weight: number;
};

export type PortionScore = {
    portion: Portion;
    correct: number;
    total: number;
    pass: number;
    passed: boolean;
    percent: number;
    previous: number | null;
    seconds: number;
    /** Seconds per question the real exam allows. */
    pace: number;
};

export type Results = {
    id: number;
    kind: TestKind;
    mode: 'exam' | 'practice';
    challenge: string | null;
    score: number;
    correct: number;
    total: number;
    pass_line: number;
    /** Both portions passed (portion tests and simulations only). */
    passed: boolean | null;
    previous: number | null;
    seconds: number;
    portions: PortionScore[];
    timing: { seconds_per_question: number; pace: number } | null;
    topics: TopicScore[];
    weakest: Topic[];
    strongest: Topic[];
    review: {
        id: number;
        n: number;
        topic: Topic;
        portion: Portion;
        prompt: string;
        options: string[];
        choice: number | null;
        right: number;
        correct: boolean;
        /** False for a pretest item: asked, not scored. */
        scored: boolean;
        explanation: string;
        source: string;
        lesson: { id: number; seconds: number | null } | null;
        /** A math question's kind in the math lab (A9.2c). */
        math?: MathKind | null;
    }[];
};

export type PortionChances = { national: number | null; state: number | null };

export type ReadinessSummary = {
    /** The weaker portion's predicted pass chance (A9.2). */
    percent: number | null;
    portions?: PortionChances;
    topics: Partial<Record<Topic, number | null>>;
    answers?: Partial<Record<Topic, number>>;
    weakest: Topic[];
    tests: number;
    ready_to_book?: boolean;
};

/** The Readiness map and What you're good at (screens 54 and 44, A9.2). */
export type ReadinessMap = {
    percent: number | null;
    portions: PortionChances;
    line: number;
    booking: {
        ready: boolean;
        national: boolean;
        state: boolean;
        simulations: number;
        needed: number;
        url: string | null;
    };
    topics: {
        topic: Topic;
        portion: Portion;
        weight: number;
        percent: number | null;
        answers: number;
        shown: boolean;
        trend: number | null;
        weak: boolean;
    }[];
    gain: { topic: Topic; portion: Portion; points: number } | null;
    computed_on: string | null;
};

export type HubTest = {
    kind: TestKind;
    topic: Topic | null;
    questions: number;
    minutes: number | null;
    best: number | null;
    last: number | null;
    open: number | null;
    last_attempt: number | null;
};

export type StudyTask = {
    id: number;
    kind: 'diagnostic' | 'lesson' | 'practice' | 'review';
    why: 'next' | 'weak' | 'review' | 'catch_up' | 'baseline';
    minutes: number;
    done: boolean;
    day: string;
    lesson: { id: number; title: string; course: string } | null;
    topic: Topic | null;
};

export type StudyToday = {
    readiness: number | null;
    portions?: PortionChances;
    ready_to_book?: boolean;
    topics: Partial<Record<Topic, number | null>>;
    weakest: Topic[];
    exam_on: string;
    days_to_exam: number;
    streak: number;
    minutes_per_day: number;
    reminder_time: string | null;
    diagnostic_done: boolean;
    continue: {
        lesson_id: number;
        title: string;
        course: string;
        position: number;
    } | null;
    tasks: StudyTask[];
    behind_minutes: number;
    plan: { id: number; reason: string; built_at: string | null };
};

/** "12:05" or "1:02:09". */
export function countdown(seconds: number): string {
    const s = Math.max(0, Math.floor(seconds));
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const r = String(s % 60).padStart(2, '0');

    return h > 0 ? `${h}:${String(m).padStart(2, '0')}:${r}` : `${m}:${r}`;
}

/** The three exam tests (A9.1). */
export type ExamKind = 'national' | 'state' | 'simulation';

export function isExam(kind: TestKind): kind is ExamKind {
    return kind === 'national' || kind === 'state' || kind === 'simulation';
}

/** The practice hub's ranking (screen 38, A9.2b). */
export type Coach = {
    drill: {
        topics: Topic[];
        questions: number;
        misses: number;
        open: number | null;
    };
    plans: { id: number; topic: Topic; done: number; steps: number }[];
    ranked: {
        mode:
            | 'diagnostic'
            | 'plan'
            | 'drill'
            | 'fix'
            | 'flashcards'
            | 'speed'
            | 'math'
            | 'simulation'
            | 'readiness'
            | 'exam_success';
        why: string;
        topic: Topic | null;
        plan_id: number | null;
        count: number | null;
        lesson_id: number | null;
    }[];
};

/** Fix a weak topic (screen 46, A9.2b). */
export type TopicPlan = {
    id: number;
    topic: Topic;
    now: number | null;
    start: number | null;
    goal: number;
    weak: boolean;
    minutes: number;
    completed: boolean;
    steps: {
        n: number;
        kind: 'lesson' | 'review' | 'practice' | 'flashcards' | 'retest';
        minutes: number;
        status: 'done' | 'current' | 'todo';
        lesson: { id: number; title: string; seconds: number | null } | null;
        attempt_id: number | null;
        results_id: number | null;
    }[];
    misses: {
        id: number;
        prompt: string;
        options: string[];
        right: number;
        explanation: string;
        source: string;
    }[];
};

export type MathKind =
    | 'proration'
    | 'commission'
    | 'ltv'
    | 'area'
    | 'cap'
    | 'net'
    | 'points';

/** A math lab problem (screen 48, A9.2c): the answers stay on the server. */
export type MathProblem = {
    kind: MathKind;
    seed: number;
    prompt: string;
    steps: { n: number; label: string; unit: string }[];
};

export type MathCheck = {
    correct: boolean;
    hint: string | null;
    answer: string | null;
    solved: boolean;
};

/** A card in the deck (screen 31, A9.2d), with its FSRS schedule. */
export type Flashcard = {
    id: number;
    source: string;
    topic: Topic | null;
    front: string;
    back: string;
    new: boolean;
    due_at: string | null;
    reps: number;
    lapses: number;
    stability: number | null;
    difficulty: number | null;
    last_reviewed_at: string | null;
};

export type Deck = {
    due: number;
    new_left: number;
    total: number;
    cards: Flashcard[];
};

/** A speed round (screen 50, A9.2d). */
export type SpeedRoundData = {
    id: number;
    seconds: number;
    best: number | null;
    statements: {
        id: number;
        prompt: string;
        answer: string;
        truth: boolean;
        topic: string;
    }[];
};

export type SpeedResult = {
    score: number;
    answered: number;
    best: number;
    record: boolean;
    misses: number;
};

/** The weekly progress report (screen 55, A9.2e). */
export type WeeklyReport = {
    week_start: string;
    week_end: string;
    first_name: string;
    minutes: number;
    goal_minutes: number;
    per_day: number[];
    questions: number;
    cards: number;
    readiness: {
        now: number | null;
        before: number | null;
        change: number | null;
    };
    lessons: number;
    hours: number;
    weakest: Topic | null;
    streak: number;
};

export type ReportShares = {
    school: boolean;
    can_share_school: boolean;
    link: string | null;
};

/** A challenge's board (screen 52, A9.3b): first names only. */
export type ChallengeBoard = {
    uuid: string;
    link: string;
    from: string;
    mine: boolean;
    questions: number;
    expires_at: string;
    expired: boolean;
    attempt_id: number | null;
    submitted: boolean;
    entries: {
        first_name: string;
        correct: number;
        total: number;
        hints: number;
        points: number;
        mine: boolean;
        finished: boolean;
    }[];
};

/** A study group with this week's leaderboard (screen 52, A9.3c): first names only. */
export type StudyGroup = {
    id: number;
    uuid: string;
    name: string;
    link: string;
    school: string | null;
    members: number;
    max: number;
    mine: boolean;
    leaderboard: {
        first_name: string;
        questions: number;
        accuracy: number | null;
        mine: boolean;
    }[];
    week_start: string;
};
