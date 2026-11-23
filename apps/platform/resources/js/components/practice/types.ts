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

export type Attempt = {
    id: number;
    kind: 'diagnostic' | 'topic';
    mode: 'exam' | 'practice';
    topic: Topic | null;
    total: number;
    answered: number;
    started_at: string;
    ends_at: string | null;
    seconds_left: number | null;
    submitted: boolean;
    questions: TestQuestion[];
};

export type TopicScore = {
    topic: Topic;
    correct: number;
    total: number;
    percent: number;
    weight: number;
};

export type Results = {
    id: number;
    kind: 'diagnostic' | 'topic';
    score: number;
    correct: number;
    total: number;
    pass_line: number;
    previous: number | null;
    seconds: number;
    topics: TopicScore[];
    weakest: Topic[];
    strongest: Topic[];
    review: {
        id: number;
        n: number;
        topic: Topic;
        prompt: string;
        options: string[];
        choice: number | null;
        right: number;
        correct: boolean;
        explanation: string;
        source: string;
    }[];
};

export type ReadinessSummary = {
    percent: number | null;
    topics: Partial<Record<Topic, number | null>>;
    weakest: Topic[];
    tests: number;
};

export type HubTest = {
    kind: 'diagnostic' | 'topic';
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
