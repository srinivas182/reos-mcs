/** Agent modes (A11.1a): what `AgentModes::show()` sends. */
export type Goal = 'launch' | 'grow' | 'lead' | 'own';

export type Flag =
    | 'part_time'
    | 'bilingual'
    | 'specialist'
    | 'referral_only'
    | 'returning';

export const GOALS: Goal[] = ['launch', 'grow', 'lead', 'own'];

export const FLAGS: Flag[] = [
    'part_time',
    'bilingual',
    'specialist',
    'referral_only',
    'returning',
];

export type ModeState = {
    goal: Goal | null;
    flags: Flag[];
    needs_setup: boolean;
    suggested_default: Goal;
    own: { unlocked: boolean; unlocks_on: string | null; years: number };
    suggestion: { goal: Goal; closings: number } | null;
    history: {
        from: Goal | null;
        to: Goal;
        reason: 'setup' | 'manual' | 'suggested';
        at: string;
    }[];
    welcome: {
        name: string;
        licence: string | null;
        state: string | null;
        brokerage: string | null;
        verified: boolean;
    };
};

/** Today for agents (A11.1b): what `AgentToday::for()` sends. */
export type TodayReason =
    | 'overdue'
    | 'deadline'
    | 'appointment'
    | 'follow_up'
    | 'plan';

export type TodayItem = {
    key: string;
    kind: 'todo' | 'deadline' | 'event' | 'plan';
    reason: TodayReason;
    title: string;
    detail: string | null;
    source: string;
    at: string | null;
    all_day: boolean;
    todo_id: number | null;
    deal: string | null;
    plan: { code: PlanTaskCode; week: number } | null;
};

export type TodayStatKey =
    | 'week'
    | 'conversations'
    | 'appointments'
    | 'pipeline'
    | 'under_contract'
    | 'hot_today'
    | 'closings_12m';

export type DayOneCode =
    | 'import_contacts'
    | 'announce_licence'
    | 'connect_tools';

export type AgentTodayData = {
    goal: Goal;
    stats: { key: TodayStatKey; value: number; target: number | null }[];
    items: TodayItem[];
    more: number;
    day_one:
        | { code: DayOneCode; minutes: number; done: boolean; link: string }[]
        | null;
    plan_week: number;
};

/** Task and phase codes in `AgentPlans::TEMPLATES`. */
export type PlanTaskCode =
    | 'connect_tools'
    | 'import_contacts'
    | 'announce_licence'
    | 'first_ce_lesson'
    | 'call_sphere'
    | 'ask_referrals'
    | 'mentor_checkin'
    | 'book_consultation'
    | 'listing_presentation'
    | 'review_contract'
    | 'plan_closing'
    | 'pipeline_review'
    | 'past_client_touch'
    | 'anniversary_touch'
    | 'map_your_hours'
    | 'write_role'
    | 'interview_candidates'
    | 'team_huddle'
    | 'choose_model'
    | 'broker_course'
    | 'business_plan'
    | 'comp_plan'
    | 'broker_licence'
    | 'go_live_checklist';

export type PlanPhase =
    | 'setup'
    | 'conversations'
    | 'appointments'
    | 'contract'
    | 'pipeline'
    | 'referrals'
    | 'repeat'
    | 'capacity'
    | 'first_hire'
    | 'team'
    | 'model'
    | 'business_plan'
    | 'launch';

/** The 90-day plan (A11.1b): what `AgentPlans::current()` sends. */
export type PlanTask = {
    code: PlanTaskCode;
    week: number;
    done: boolean;
    auto: boolean;
    repeats: boolean;
    link: string | null;
};

export type AgentPlan = {
    goal: Goal;
    starts_on: string;
    ends_on: string;
    week: number;
    weeks: number;
    phases: { code: PlanPhase; from: number; to: number; current: boolean }[];
    targets: Partial<Record<ScoreMetric, number>>;
    tasks: PlanTask[];
    done: number;
    total: number;
    promised: boolean;
};

export type ScoreMetric =
    | 'conversations'
    | 'appointments'
    | 'clients'
    | 'ce_hours';

export type Scorecard = {
    week_of: string;
    metrics: { key: ScoreMetric; done: number; target: number }[];
    fix: ScoreMetric | null;
};
