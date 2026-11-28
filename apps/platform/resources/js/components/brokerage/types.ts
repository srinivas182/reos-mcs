export type BrokerRoleChoice =
    | 'designated_broker'
    | 'office_admin'
    | 'team_leader';

export type TrecBrokerage = {
    number: string;
    name: string;
    kind: 'entity' | 'individual';
    sponsored: number;
    on_reos: boolean;
};

export type BrokerLookup = {
    licence: {
        number: string;
        name: string;
        status: string | null;
        active: boolean;
        expires_on: string | null;
    };
    name_matches: boolean;
    brokerages: TrecBrokerage[];
};

export type BrokerConfirmed = {
    verification: string | null;
    role: BrokerRoleChoice;
    brokerage: string | null;
    sponsored: number | null;
};

export type SetupStepKey =
    | 'verify'
    | 'agents'
    | 'seats'
    | 'manual'
    | 'advertising'
    | 'mentors';

export type BrokerageSetupData = {
    trec_number: string | null;
    done: number;
    total: number;
    steps: { key: SetupStepKey; done: boolean; href: string }[];
};

export type RosterState =
    | 'member'
    | 'invited'
    | 'on_reos'
    | 'contact'
    | 'needs_contact';

export type TrecRosterData = {
    trec_number: string | null;
    counts: {
        sponsored: number;
        member: number;
        invited: number;
        on_reos: number;
        contact: number;
        needs_contact: number;
    };
    agents: {
        licence: string;
        name: string;
        type: string;
        expires_on: string | null;
        state: RosterState;
        contact: string | null;
    }[];
};

export type Payer = 'organisation' | 'member' | 'split';

export type SeatsPlan = {
    seats: number | null;
    used: number;
    pending: number;
    left: number | null;
    payer: Payer;
    credits_per_seat: number;
    credits_pooled: boolean;
    pool: number | null;
    candidate_pool: number;
    teams: number;
    chosen: boolean;
};

export type TrecConflict = {
    rule: string;
    citation: string;
    url: string | null;
    excerpt: string;
    rule_text: string;
};

export type BrokerageRule = {
    id: number;
    topic: string;
    text: string;
    conflict: TrecConflict | null;
    updated_at: string | null;
};

export type PoliciesData = {
    manual: { version: number; file_name: string; conflicts: number } | null;
    advertising: {
        approval_new_agents: boolean;
        approval_all: boolean;
        banned: number;
    };
    rules: BrokerageRule[];
    can_manage: boolean;
};

export type Duty =
    | 'approve_posts'
    | 'answer_escalations'
    | 'manage_policies'
    | 'sponsor_agents';

export type StaffPerson = {
    user_id: number;
    name: string;
    title: 'designated_broker' | 'office_admin' | 'team_leader';
    team: string | null;
    duties: Duty[];
    editable: boolean;
};

export type ApprovalKind = 'post' | 'page' | 'question';

export type ApprovalItem = {
    kind: ApprovalKind;
    id: string;
    agent: string;
    summary: string;
    waiting_since: string | null;
    hours: number;
    overdue: boolean;
    detail: Record<string, unknown>;
};

export type ApprovalsQueueData = {
    brokerage: string;
    scope: 'brokerage' | 'team';
    team: string | null;
    fallback_hours: number;
    can: { posts: boolean; questions: boolean; settings: boolean };
    counts: {
        posts: number;
        pages: number;
        questions: number;
        overdue: number;
    };
    items: ApprovalItem[];
};

export type OversightSignal =
    | 'quiet'
    | 'no_deal'
    | 'ce_behind'
    | 'plan_behind'
    | 'deadline_missed'
    | 'new';

export type OversightAgent = {
    id: number;
    name: string;
    team: string | null;
    goal: string;
    last_active: string | null;
    signals: OversightSignal[];
    deals: { active: number; under_contract: number; closed_90: number };
    ce: {
        due_on: string | null;
        hours_left: number;
        first_renewal: boolean;
        complete: boolean;
    } | null;
};

export type RiskDeal = {
    id: string;
    agent: string;
    agent_id: number;
    stage: string;
    city: string | null;
    address: string | null;
    reasons: ('date_soon' | 'date_missed' | 'stuck')[];
    next_date: { kind: string; due_on: string } | null;
};

export type OversightBoardData = {
    brokerage: string;
    scope: 'brokerage' | 'team';
    team: string | null;
    settings: { quiet_days: number; no_deal_days: number; new_days: number };
    can_change: boolean;
    counts: { agents: number; need_help: number; deals_at_risk: number };
    agents: OversightAgent[];
    at_risk: RiskDeal[];
};

export type OversightProfile = {
    agent: OversightAgent;
    plan: {
        goal: string;
        week: number;
        weeks: number;
        done: number;
        total: number;
    } | null;
    activity: {
        conversations: number;
        appointments: number;
        posts: number;
        ce_hours: number;
    };
    deals: {
        id: string;
        stage: string;
        side: string;
        city: string | null;
        address: string | null;
        closed_at: string | null;
    }[];
    licences: {
        state: string;
        number: string;
        status: string;
        expires_on: string | null;
    }[];
};

export type CeStatus =
    | 'done'
    | 'on_track'
    | 'due'
    | 'behind'
    | 'overdue'
    | 'no_licence';

export type CeRow = {
    id: number;
    name: string;
    team: string | null;
    licence: string | null;
    status: CeStatus;
    due_on: string | null;
    days_left: number | null;
    hours_required: number;
    hours_done: number;
    hours_left: number;
    sae: boolean;
    reminded_at: string | null;
    assignments: { course: string; due_on: string | null; done: boolean }[];
};

export type CeTable = {
    brokerage: string;
    scope: 'brokerage' | 'team';
    team: string | null;
    counts: { agents: number; due: number; done: number };
    rows: CeRow[];
    courses: {
        id: number;
        title: string;
        type: string;
        hours: string | null;
    }[];
};

export type SponsorshipBoard = {
    brokerage: string;
    trec_number: string | null;
    changes: {
        id: string;
        kind: 'join' | 'release';
        agent: string;
        agent_id: number;
        effective_on: string;
        handover_to: string | null;
        deals: number;
        status: 'recorded' | 'done' | 'confirmed';
        confirmed_at: string | null;
    }[];
    agents: { id: number; name: string; open_deals: number }[];
    handover: { id: number; name: string }[];
};

/** A count and dollar volume (A16.3). */
export type ReportLine = { count: number; volume: number };

export type ReportPeriod =
    | 'this_month'
    | 'last_month'
    | 'this_quarter'
    | 'this_year'
    | 'last_year';

/** Brokerage › Reports: closings matched to the transaction tool (A16.3). */
export type ClosingsReport = {
    brokerage: string;
    scope: 'brokerage' | 'team';
    team: string | null;
    period: ReportPeriod;
    from: string;
    to: string;
    totals: ReportLine;
    by_agent: ({
        id: number;
        name: string;
        team: string | null;
    } & ReportLine)[];
    by_team: ({ name: string | null } & ReportLine)[];
    by_month: ({ month: string } & ReportLine)[];
    tools: ({
        connection: number;
        tool: string;
        name: string;
        label: string | null;
        last_synced_at: string | null;
        stale: boolean;
    } & ReportLine)[];
    reos_only: ReportLine & {
        deals: {
            id: string;
            agent: string;
            property: string;
            price: number | null;
            closed_at: string;
        }[];
    };
};

/** Training: each assigned course's progress (A16.3). */
export type TrainingReport = {
    brokerage: string;
    scope: 'brokerage' | 'team';
    team: string | null;
    courses: {
        id: number;
        title: string;
        type: string;
        ce_hours: string | null;
        assigned: number;
        done: number;
        overdue: number;
    }[];
};

/** The broker's morning brief (A16.3): counts and stages only. */
export type BrokerBrief = {
    date: string;
    brokerage: string;
    scope: 'brokerage' | 'team';
    team: string | null;
    settings: OversightBoardData['settings'];
    need_help: {
        count: number;
        agents: { id: number; name: string; signals: OversightSignal[] }[];
    };
    at_risk: {
        count: number;
        deals: {
            id: string;
            agent: string;
            stage: string;
            city: string | null;
            reasons: string[];
        }[];
    };
    approvals: { waiting: number; overdue: number };
    ce_due: number | null;
    closings: ReportLine;
};
