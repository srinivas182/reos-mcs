import type { AgentsInAreaView, SentRequest } from './types';

/** Maya Chen (Chen Home Lending, Listed) in Katy at the end of November. */
export const mayaAgents: AgentsInAreaView = {
    areas: [
        { id: 1, label: 'Katy' },
        { id: 2, label: '15 miles around 77494' },
    ],
    filters: { level: null, area: null },
    counts: { new: 1, active: 2, top: 1 },
    agents: [
        {
            id: 11,
            name: 'James M.',
            brokerage: 'Lone Star Realty',
            level: 'top',
            sides: 14,
            cities: ['Katy', 'Fulshear'],
            request: null,
            can_request: true,
        },
        {
            id: 12,
            name: 'Maria G.',
            brokerage: 'Lone Star Realty',
            level: 'active',
            sides: 3,
            cities: ['Katy'],
            request: {
                id: '01936b7a-0000-7000-8000-000000000501',
                status: 'pending',
                sent_at: '2026-11-28T10:00:00-06:00',
            },
            can_request: false,
        },
        {
            id: 13,
            name: 'Dana B.',
            brokerage: 'Bayou City Homes',
            level: 'active',
            sides: 2,
            cities: ['Katy'],
            request: null,
            can_request: true,
        },
        {
            id: 14,
            name: 'Priya S.',
            brokerage: 'Lone Star Realty',
            level: 'new',
            sides: 1,
            cities: ['Katy'],
            request: null,
            can_request: true,
        },
    ],
    usage: {
        tier: 'basic',
        limit: 5,
        used: 3,
        left: 2,
        resets_on: '2026-12-01',
    },
};

export const mayaUsedUp: AgentsInAreaView = {
    ...mayaAgents,
    usage: { ...mayaAgents.usage, used: 5, left: 0 },
};

export const mayaNoAgents: AgentsInAreaView = {
    ...mayaAgents,
    counts: { new: 0, active: 0, top: 0 },
    agents: [],
};

export const mayaSent: SentRequest[] = [
    {
        id: '01936b7a-0000-7000-8000-000000000501',
        agent: 'Maria G.',
        status: 'pending',
        message: 'Happy to help your buyers in Katy with pre-approvals.',
        sent_at: '2026-11-28T10:00:00-06:00',
        expires_at: '2026-12-12T10:00:00-06:00',
    },
    {
        id: '01936b7a-0000-7000-8000-000000000502',
        agent: 'Omar H.',
        status: 'expired',
        message: null,
        sent_at: '2026-11-02T09:00:00-06:00',
        expires_at: '2026-11-16T09:00:00-06:00',
    },
];
