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
