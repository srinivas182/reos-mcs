/** Teams (A17.3): lead routing and split sheets. */
export type RoutingRules = {
    sources: string[];
    method: 'first_to_claim' | 'round_robin';
    claim_minutes: number;
    fallback: 'round_robin' | 'leader';
    off_start: string;
    off_end: string;
};

export type RotationMember = {
    id: number;
    name: string;
    active: boolean;
    languages: string[];
    last_assigned_at: string | null;
};

export type TeamLead = {
    id: string;
    source: string;
    name: string;
    language: string | null;
    status: string;
    how: string | null;
    agent: string | null;
    claim_by: string | null;
    created_at: string;
    auto_reply: string | null;
};

export type RoutingBoard = {
    team: string;
    rules: RoutingRules;
    members: RotationMember[];
    leads: TeamLead[];
};

export type SplitSheetRow = {
    id: string;
    agent: string;
    property: string;
    closing_on: string;
    lead_source: string | null;
    gross: number;
    brokerage: number;
    team: number;
    agent_share: number;
    status: string;
    signed_at: string | null;
    exported_at: string | null;
};

export type TeamAgreement = {
    brokerage_percent: number;
    team_percent: number;
    agent_percent: number;
};

export type SplitsBoard = {
    team: string;
    agreement: TeamAgreement;
    sheets: SplitSheetRow[];
    agents: { id: number; name: string }[];
};
