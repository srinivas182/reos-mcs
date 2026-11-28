import type { OpenAgent, SponsoredBoard } from './types';

/** Lone Star Realty sponsors Ana and Ben; Cara passed. */
export const loneStarSponsored: SponsoredBoard = {
    pool: 995,
    candidates: [
        {
            id: '01936b7a-0000-7000-8000-000000000901',
            candidate: 'Ana R.',
            cap: 200,
            used: 5,
            status: 'active',
            end_reason: null,
            since: '2026-11-23',
        },
        {
            id: '01936b7a-0000-7000-8000-000000000902',
            candidate: 'Ben O.',
            cap: 100,
            used: 100,
            status: 'used_up',
            end_reason: null,
            since: '2026-11-02',
        },
        {
            id: '01936b7a-0000-7000-8000-000000000903',
            candidate: 'Cara D.',
            cap: 100,
            used: 64,
            status: 'ended',
            end_reason: 'passed',
            since: '2026-10-12',
        },
    ],
};

export const openAgents: OpenAgent[] = [
    {
        id: 21,
        name: 'Omar H.',
        closings: 9,
        focus: 'grow',
        reasons: ['closings', 'city'],
        invited_at: null,
        conversation: null,
    },
    {
        id: 22,
        name: 'Lee W.',
        closings: 2,
        focus: 'launch',
        reasons: ['new'],
        invited_at: '2026-11-20T10:00:00-06:00',
        conversation: null,
    },
];
