/** Daily goals, points and rewards (A13.3a), as the pages and API send them. */
export type GoalKey = 'conversations' | 'ce_lesson' | 'follow_ups' | 'post';
export type Mode = 'launch' | 'grow' | 'lead' | 'own';

export type GoalRow = {
    key: GoalKey;
    done: number;
    target: number;
    points: number;
    met: boolean;
    bonus: number;
};

export type Today = {
    day: string;
    mode: string;
    goals: GoalRow[];
    points_today: number;
    bonus_today: number;
    all_done: boolean;
    all_done_points: number;
    streak: number;
    best_streak: number;
    balance: number;
    campaigns: {
        id: string;
        title: string;
        goal: string;
        multiplier: number;
        ends_on: string;
    }[];
};

export type RewardOption = {
    price: number;
    kind: 'credit_pack' | 'plan';
    name: string;
    points: number;
    credits: number | null;
    interval: 'once' | 'month' | 'year';
    affordable: boolean;
};

export type HistoryRow = {
    reason: string;
    points: number;
    memo: string | null;
    at: string;
};

export type RewardsSummary = {
    balance: number;
    value_cents: number;
    options: RewardOption[];
    history: HistoryRow[];
};

export type Campaign = {
    id: string;
    title: string;
    goal: string;
    multiplier: number;
    starts_on: string;
    ends_on: string;
    budget: number;
    spent: number;
    active: boolean;
    closed: boolean;
};

export type ModeTargets = Record<
    Mode,
    { targets: Record<GoalKey, number>; own: boolean }
>;

export type Rates = {
    points: Record<GoalKey, number>;
    all_done: number;
    daily_cap: number;
    targets: Record<Mode, Record<GoalKey, number>>;
};
