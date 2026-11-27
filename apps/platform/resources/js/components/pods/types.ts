/** Pods and brokerage challenges (A13.2), as the API and pages send them. */
export type Metric = 'conversations' | 'appointments' | 'clients' | 'ce_hours';

export type PodMemberRow = {
    id: number;
    name: string;
    me: boolean;
    goal: string | null;
    metrics: { key: string; done: number; target: number }[];
    shares: string[];
};

export type PodView = {
    status: 'none' | 'waiting' | 'member';
    waiting_since: string | null;
    pod: {
        id: string;
        name: string;
        goal: string;
        call: {
            day: string;
            time: string;
            timezone: string;
            next: string | null;
            session: string | null;
        };
        conversation: string | null;
        week_of: string;
        members: PodMemberRow[];
    } | null;
};

export type ChallengeMetric =
    | 'conversations'
    | 'appointments'
    | 'ce_hours'
    | 'posts';

export type BoardRow = {
    id: number;
    name: string;
    me: boolean;
    value: number;
    improved: number;
    personal_best: boolean;
};

export type ChallengeRow = {
    id: string;
    title: string;
    metric: ChallengeMetric;
    starts_on: string;
    ends_on: string;
    joined: boolean;
    open: boolean;
    entrants: number;
    total: BoardRow[];
    improved: BoardRow[];
};
