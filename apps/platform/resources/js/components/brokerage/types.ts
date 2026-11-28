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
