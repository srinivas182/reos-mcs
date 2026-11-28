import type { OversightBoardData, OversightProfile } from './types';

/** Lone Star Realty on a Monday (A16.1): James has gone quiet; Priya's option period ends tomorrow. */
export const loneStarBoard: OversightBoardData = {
    brokerage: 'Lone Star Realty',
    scope: 'brokerage',
    team: null,
    settings: { quiet_days: 14, no_deal_days: 90, new_days: 90 },
    can_change: true,
    counts: { agents: 2, need_help: 1, deals_at_risk: 1 },
    agents: [
        {
            id: 3,
            name: 'James Miller',
            team: null,
            goal: 'grow',
            last_active: '2026-10-31T15:00:00Z',
            signals: ['quiet', 'no_deal', 'ce_behind'],
            deals: { active: 0, under_contract: 0, closed_90: 0 },
            ce: {
                due_on: '2026-12-31',
                hours_left: 18,
                first_renewal: false,
                complete: false,
            },
        },
        {
            id: 4,
            name: 'Priya Shah',
            team: 'Okoye Team',
            goal: 'launch',
            last_active: '2026-11-30T15:00:00Z',
            signals: [],
            deals: { active: 1, under_contract: 1, closed_90: 2 },
            ce: {
                due_on: '2027-06-30',
                hours_left: 40,
                first_renewal: true,
                complete: false,
            },
        },
    ],
    at_risk: [
        {
            id: 'd1',
            agent: 'Priya Shah',
            agent_id: 4,
            stage: 'under_contract',
            city: 'Katy',
            address: '1420 Pecan St, Katy',
            reasons: ['date_soon'],
            next_date: { kind: 'option_period', due_on: '2026-12-01' },
        },
    ],
};

export const priyaProfile: OversightProfile = {
    agent: loneStarBoard.agents[1],
    plan: { goal: 'launch', week: 3, weeks: 13, done: 6, total: 26 },
    activity: { conversations: 24, appointments: 5, posts: 4, ce_hours: 6 },
    deals: [
        {
            id: 'd1',
            stage: 'under_contract',
            side: 'buy',
            city: 'Katy',
            address: '1420 Pecan St, Katy',
            closed_at: null,
        },
        {
            id: 'd2',
            stage: 'active',
            side: 'sell',
            city: 'Katy',
            address: null,
            closed_at: null,
        },
    ],
    licences: [
        {
            state: 'TX',
            number: '0812345',
            status: 'active',
            expires_on: '2027-06-30',
        },
    ],
};
