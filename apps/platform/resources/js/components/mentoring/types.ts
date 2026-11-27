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
};
