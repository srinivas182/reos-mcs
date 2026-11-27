import type {
    Campaign,
    ModeTargets,
    Rates,
    RewardsSummary,
    Today,
} from './types';

/** James Miller's Friday: conversations done, follow-ups and a post to go (REOS demo content). */
export const todayFixture = (over: Partial<Today> = {}): Today => ({
    day: '2026-11-27',
    mode: 'launch',
    goals: [
        {
            key: 'conversations',
            done: 5,
            target: 5,
            points: 20,
            met: true,
            bonus: 0,
        },
        {
            key: 'ce_lesson',
            done: 1,
            target: 1,
            points: 25,
            met: true,
            bonus: 25,
        },
        {
            key: 'follow_ups',
            done: 1,
            target: 3,
            points: 15,
            met: false,
            bonus: 0,
        },
        { key: 'post', done: 0, target: 1, points: 15, met: false, bonus: 0 },
    ],
    points_today: 45,
    bonus_today: 25,
    all_done: false,
    all_done_points: 25,
    streak: 4,
    best_streak: 9,
    balance: 3120,
    campaigns: [
        {
            id: 'c1',
            title: 'Double points on CE in November',
            goal: 'ce_lesson',
            multiplier: 2,
            ends_on: '2026-11-30',
        },
    ],
    ...over,
});

export const rewardsFixture = (
    over: Partial<RewardsSummary> = {},
): RewardsSummary => ({
    balance: 3120,
    value_cents: 3120,
    options: [
        {
            price: 11,
            kind: 'credit_pack',
            name: '1,000 AI credits',
            points: 1500,
            credits: 1000,
            interval: 'once',
            affordable: true,
        },
        {
            price: 12,
            kind: 'plan',
            name: 'Agent Core',
            points: 4900,
            credits: null,
            interval: 'month',
            affordable: false,
        },
    ],
    history: [
        {
            reason: 'points_awarded',
            points: 25,
            memo: 'goal:2026-11-27:ce_lesson',
            at: '2026-11-27T15:10:00Z',
        },
        {
            reason: 'points_bonus',
            points: 25,
            memo: 'campaign:3:2026-11-27:ce_lesson',
            at: '2026-11-27T15:10:00Z',
        },
        {
            reason: 'points_awarded',
            points: 20,
            memo: 'goal:2026-11-27:conversations',
            at: '2026-11-27T14:30:00Z',
        },
        {
            reason: 'points_redeemed',
            points: -4000,
            memo: 'session:8f2c',
            at: '2026-11-20T18:00:00Z',
        },
    ],
    ...over,
});

export const modesFixture = (): ModeTargets => ({
    launch: {
        targets: { conversations: 5, ce_lesson: 1, follow_ups: 3, post: 1 },
        own: true,
    },
    grow: {
        targets: { conversations: 10, ce_lesson: 0, follow_ups: 5, post: 1 },
        own: false,
    },
    lead: {
        targets: { conversations: 8, ce_lesson: 0, follow_ups: 5, post: 1 },
        own: false,
    },
    own: {
        targets: { conversations: 5, ce_lesson: 0, follow_ups: 5, post: 1 },
        own: false,
    },
});

export const campaignsFixture = (): Campaign[] => [
    {
        id: '3a1b2c3d-4e5f-4a6b-8c7d-9e0f1a2b3c4d',
        title: 'Double points on CE in November',
        goal: 'ce_lesson',
        multiplier: 2,
        starts_on: '2026-11-01',
        ends_on: '2099-11-30',
        budget: 50000,
        spent: 6250,
        active: true,
        closed: false,
    },
    {
        id: '4b2c3d4e-5f6a-4b7c-9d8e-0f1a2b3c4d5e',
        title: 'October posting sprint',
        goal: 'post',
        multiplier: 3,
        starts_on: '2026-10-01',
        ends_on: '2026-10-31',
        budget: 20000,
        spent: 20000,
        active: false,
        closed: true,
    },
];

export const ratesFixture = (): Rates => ({
    points: { conversations: 20, ce_lesson: 25, follow_ups: 15, post: 15 },
    all_done: 25,
    daily_cap: 200,
    targets: {
        launch: { conversations: 5, ce_lesson: 1, follow_ups: 3, post: 1 },
        grow: { conversations: 10, ce_lesson: 0, follow_ups: 5, post: 1 },
        lead: { conversations: 8, ce_lesson: 0, follow_ups: 5, post: 1 },
        own: { conversations: 5, ce_lesson: 0, follow_ups: 5, post: 1 },
    },
});
