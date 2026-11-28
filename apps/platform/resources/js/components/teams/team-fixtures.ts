import type {
    RoutingBoard,
    SplitSheetRow,
    SplitsBoard,
    TeamLead,
} from './types';

const rosa: TeamLead = {
    id: '01936b7a-0000-7000-8000-000000001001',
    source: 'zillow',
    name: 'Rosa Lima',
    language: 'es',
    status: 'claimed',
    how: 'claim',
    agent: 'Maria Garcia',
    claim_by: null,
    created_at: '2026-11-23T10:00:00-06:00',
    auto_reply: null,
};

/** Okoye Team: first to claim in 5 minutes, Tom out of rotation. */
export const okoyeRouting: RoutingBoard = {
    team: 'Okoye Team',
    rules: {
        sources: [],
        method: 'first_to_claim',
        claim_minutes: 5,
        fallback: 'round_robin',
        off_start: '20:00',
        off_end: '08:00',
    },
    members: [
        {
            id: 1,
            name: 'Maria Garcia',
            active: true,
            languages: ['en', 'es'],
            last_assigned_at: '2026-11-23T10:02:00-06:00',
        },
        {
            id: 2,
            name: 'Priya Shah',
            active: true,
            languages: ['en'],
            last_assigned_at: null,
        },
        {
            id: 3,
            name: 'Tom Reyes',
            active: false,
            languages: ['en'],
            last_assigned_at: null,
        },
    ],
    leads: [
        rosa,
        {
            ...rosa,
            id: '01936b7a-0000-7000-8000-000000001002',
            name: 'Sam Hill',
            source: 'website',
            language: null,
            status: 'assigned',
            how: 'fallback',
            agent: 'Priya Shah',
        },
        {
            ...rosa,
            id: '01936b7a-0000-7000-8000-000000001003',
            name: 'Night Owl',
            source: 'realtor_com',
            status: 'offered',
            how: null,
            agent: null,
            auto_reply: 'Thanks for contacting Okoye Team.',
        },
    ],
};

export const priyaSheet: SplitSheetRow = {
    id: '01936b7a-0000-7000-8000-000000001101',
    agent: 'Priya Shah',
    property: '1420 Pecan St, Katy',
    closing_on: '2026-11-20',
    lead_source: 'zillow',
    gross: 1200000,
    brokerage: 240000,
    team: 360000,
    agent_share: 600000,
    status: 'sent',
    signed_at: null,
    exported_at: null,
};

export const okoyeSplits: SplitsBoard = {
    team: 'Okoye Team',
    agreement: { brokerage_percent: 20, team_percent: 30, agent_percent: 50 },
    sheets: [
        priyaSheet,
        {
            ...priyaSheet,
            id: '01936b7a-0000-7000-8000-000000001102',
            property: '9 Oak Ln, Katy',
            status: 'signed',
            signed_at: '2026-11-21T09:00:00-06:00',
            exported_at: '2026-11-22T09:00:00-06:00',
        },
    ],
    agents: [
        { id: 2, name: 'Priya Shah' },
        { id: 1, name: 'Maria Garcia' },
    ],
};

export const priyaOffered: TeamLead[] = [
    {
        ...rosa,
        id: '01936b7a-0000-7000-8000-000000001004',
        name: 'Ray Ng',
        status: 'offered',
        how: null,
        agent: null,
        claim_by: '2026-11-23T10:05:00-06:00',
    },
];
