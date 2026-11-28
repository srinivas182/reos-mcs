import type { CeTable, SponsorshipBoard } from './types';

/** Lone Star Realty's CE on 30 November 2026 (A16.2). */
export const loneStarCe: CeTable = {
    brokerage: 'Lone Star Realty',
    scope: 'brokerage',
    team: null,
    counts: { agents: 3, due: 2, done: 0 },
    rows: [
        {
            id: 3,
            name: 'James Miller',
            team: null,
            licence: '0798765',
            status: 'behind',
            due_on: '2026-12-31',
            days_left: 31,
            hours_required: 18,
            hours_done: 0,
            hours_left: 18,
            sae: false,
            reminded_at: null,
            assignments: [],
        },
        {
            id: 4,
            name: 'Priya Shah',
            team: 'Okoye Team',
            licence: '0812345',
            status: 'behind',
            due_on: '2027-03-31',
            days_left: 121,
            hours_required: 98,
            hours_done: 30,
            hours_left: 68,
            sae: true,
            reminded_at: '2026-11-23T15:00:00Z',
            assignments: [
                { course: 'Legal Update I', due_on: '2026-12-20', done: false },
            ],
        },
        {
            id: 5,
            name: 'Dana Brooks',
            team: null,
            licence: '0711111',
            status: 'on_track',
            due_on: '2027-10-31',
            days_left: 335,
            hours_required: 18,
            hours_done: 6,
            hours_left: 12,
            sae: false,
            reminded_at: null,
            assignments: [],
        },
    ],
    courses: [{ id: 1, title: 'Legal Update I', type: 'ce', hours: '4' }],
};

export const loneStarSponsorships: SponsorshipBoard = {
    brokerage: 'Lone Star Realty',
    trec_number: '9012345',
    changes: [
        {
            id: 'c1',
            kind: 'join',
            agent: 'Omar Haddad',
            agent_id: 6,
            effective_on: '2026-11-20',
            handover_to: null,
            deals: 0,
            status: 'confirmed',
            confirmed_at: '2026-11-21T12:00:00Z',
        },
    ],
    agents: [
        { id: 3, name: 'James Miller', open_deals: 1 },
        { id: 5, name: 'Dana Brooks', open_deals: 0 },
    ],
    handover: [
        { id: 3, name: 'James Miller' },
        { id: 5, name: 'Dana Brooks' },
        { id: 1, name: 'Linda Park' },
    ],
};
