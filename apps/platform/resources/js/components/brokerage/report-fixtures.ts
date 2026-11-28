import type { BrokerBrief, ClosingsReport, TrainingReport } from './types';

/** Lone Star Realty in November 2026: James's sale closed in SkySlope, Maria's only in REOS. */
export const loneStarClosings: ClosingsReport = {
    brokerage: 'Lone Star Realty',
    scope: 'brokerage',
    team: null,
    period: 'this_month',
    from: '2026-11-01',
    to: '2026-11-30',
    totals: { count: 2, volume: 689000 },
    by_agent: [
        { id: 2, name: 'James Miller', team: null, count: 1, volume: 389000 },
        {
            id: 3,
            name: 'Maria Garcia',
            team: 'Okoye Team',
            count: 1,
            volume: 300000,
        },
    ],
    by_team: [
        { name: null, count: 1, volume: 389000 },
        { name: 'Okoye Team', count: 1, volume: 300000 },
    ],
    by_month: [{ month: '2026-11', count: 2, volume: 689000 }],
    tools: [
        {
            connection: 7,
            tool: 'skyslope',
            name: 'SkySlope',
            label: 'Lone Star Realty',
            last_synced_at: '2026-11-23T08:45:00-06:00',
            stale: false,
            count: 1,
            volume: 389000,
        },
    ],
    reos_only: {
        count: 1,
        volume: 300000,
        deals: [
            {
                id: '01936b7a-0000-7000-8000-000000000301',
                agent: 'Maria Garcia',
                property: '310 Bluebonnet Ln',
                price: 300000,
                closed_at: '2026-11-20T12:00:00-06:00',
            },
        ],
    },
};

/** Everything in SkySlope: nothing to square. */
export const loneStarMatched: ClosingsReport = {
    ...loneStarClosings,
    totals: { count: 1, volume: 389000 },
    by_agent: [loneStarClosings.by_agent[0]!],
    by_team: [loneStarClosings.by_team[0]!],
    by_month: [{ month: '2026-11', count: 1, volume: 389000 }],
    reos_only: { count: 0, volume: 0, deals: [] },
};

/** No tool connected, nothing closed yet. */
export const loneStarEmpty: ClosingsReport = {
    ...loneStarClosings,
    totals: { count: 0, volume: 0 },
    by_agent: [],
    by_team: [],
    by_month: [{ month: '2026-11', count: 0, volume: 0 }],
    tools: [],
    reos_only: { count: 0, volume: 0, deals: [] },
};

export const loneStarTraining: TrainingReport = {
    brokerage: 'Lone Star Realty',
    scope: 'brokerage',
    team: null,
    courses: [
        {
            id: 11,
            title: 'Legal Update I',
            type: 'ce',
            ce_hours: '4.0',
            assigned: 3,
            done: 1,
            overdue: 1,
        },
        {
            id: 12,
            title: 'Listing presentations that win',
            type: 'elective',
            ce_hours: null,
            assigned: 5,
            done: 5,
            overdue: 0,
        },
    ],
};

/** Linda's Monday morning. */
export const lindaBrief: BrokerBrief = {
    date: '2026-11-23',
    brokerage: 'Lone Star Realty',
    scope: 'brokerage',
    team: null,
    settings: { quiet_days: 14, no_deal_days: 90, new_days: 90 },
    need_help: {
        count: 2,
        agents: [
            { id: 3, name: 'Maria Garcia', signals: ['quiet', 'no_deal'] },
            { id: 4, name: 'Dana Brooks', signals: ['ce_behind'] },
        ],
    },
    at_risk: {
        count: 1,
        deals: [
            {
                id: '01936b7a-0000-7000-8000-000000000302',
                agent: 'James Miller',
                stage: 'under_contract',
                city: 'Katy',
                reasons: ['date_soon'],
            },
        ],
    },
    approvals: { waiting: 3, overdue: 1 },
    ce_due: 2,
    closings: { count: 1, volume: 389000 },
};

export const quietBrief: BrokerBrief = {
    ...lindaBrief,
    need_help: { count: 0, agents: [] },
    at_risk: { count: 0, deals: [] },
    approvals: { waiting: 0, overdue: 0 },
    ce_due: 0,
};
