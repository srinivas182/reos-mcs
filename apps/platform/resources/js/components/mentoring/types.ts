/** The broker's mentor program (A13.1a), as the page sends it. */
export type Topic =
    | 'first_deals'
    | 'buyers'
    | 'listings'
    | 'contracts'
    | 'negotiation'
    | 'marketing'
    | 'investors'
    | 'leading_a_team';

export type Who = 'everyone' | 'assigned';

export type MentorRow = {
    id: number;
    name: string;
    topics: Topic[];
    price_30: number;
    price_60: number;
    points: boolean;
    max_mentees: number;
    who: Who;
    mentees: number;
    rating: number | null;
    ratings: number;
    sessions: number;
    hours_set: boolean;
};

export type Goal = 'launch' | 'grow' | 'lead' | 'own';

export type AgentRow = {
    id: number;
    name: string;
    goal: Goal;
    mentor: { id: number; name: string } | null;
    change_requested: boolean;
};

export type MatchRow = {
    id: number;
    name: string;
    score: number;
    reasons: ('language' | 'focus' | 'capacity')[];
    room: number;
};

export type MentorSettings = {
    topics: Topic[];
    price_30_cents: number;
    price_60_cents: number;
    points: boolean;
    max_mentees: number;
    who: Who;
};

export type MentorProgramData = {
    brokerage: string;
    mentors: MentorRow[];
    agents: AgentRow[];
    topics: Topic[];
    reviews: ReviewRow[];
    /** Points sessions with the brokerage's mentors this month, which it funds (A17.1). */
    points?: { sessions: number; funded: number };
};

export type ReviewRow = {
    id: string;
    mentor: string;
    agent: string;
    stars: number;
    tags: string[];
    comment: string | null;
    rated_at: string | null;
};

/** The mentor's workspace (A13.1b). */
export type Signal = 'quiet' | 'first_session' | 'behind' | 'ok';

export type MenteeRow = {
    id: number;
    name: string;
    goal: Goal;
    assigned: boolean;
    signal: Signal;
    last_active: string | null;
    next_session: string | null;
};

export type WorkspaceSession = {
    uuid: string;
    agent: string;
    agent_id: number;
    starts_at: string;
    minutes: number;
    topic: string;
    status: string;
    session: string | null;
    summary: string | null;
    tasks_sent: boolean;
};

export type MentorTodayData = {
    brokerage: string;
    mentees: number;
    sessions_this_week: number;
    needs: MenteeRow[];
    upcoming: WorkspaceSession[];
    wrap_ups: WorkspaceSession[];
};

export type Metric = 'conversations' | 'appointments' | 'clients' | 'ce_hours';

export type MenteeDetailData = {
    mentee: MenteeRow;
    plan: { week: number; weeks: number; done: number; total: number };
    scorecard: { key: Metric; done: number; target: number }[];
    fix: string | null;
    deals: { active: number; under_contract: number; closed: number };
    sessions: WorkspaceSession[];
    goals: {
        id: number;
        title: string;
        due_date: string | null;
        done: boolean;
    }[];
    notes: { id: string; body: string; created_at: string | null }[];
};
