import type {
    MenteeDetailData,
    MentorProgramData,
    MentorTodayData,
} from './types';

export const program = (
    over: Partial<MentorProgramData> = {},
): MentorProgramData => ({
    brokerage: 'Lone Star Realty',
    topics: [
        'first_deals',
        'buyers',
        'listings',
        'contracts',
        'negotiation',
        'marketing',
        'investors',
        'leading_a_team',
    ],
    mentors: [
        {
            id: 11,
            name: 'Sarah Kim',
            topics: ['first_deals', 'contracts'],
            price_30: 0,
            price_60: 0,
            points: true,
            max_mentees: 5,
            who: 'everyone',
            mentees: 3,
            rating: 4.8,
            ratings: 12,
            sessions: 31,
            hours_set: true,
        },
        {
            id: 12,
            name: 'Dana Brooks',
            topics: ['listings', 'negotiation'],
            price_30: 4000,
            price_60: 7500,
            points: true,
            max_mentees: 4,
            who: 'everyone',
            mentees: 4,
            rating: null,
            ratings: 0,
            sessions: 0,
            hours_set: false,
        },
    ],
    agents: [
        {
            id: 21,
            name: 'Priya Shah',
            goal: 'launch',
            mentor: null,
            change_requested: false,
        },
        {
            id: 22,
            name: 'Tom Reyes',
            goal: 'grow',
            mentor: { id: 11, name: 'Sarah Kim' },
            change_requested: true,
        },
    ],
    reviews: [],
    ...over,
});

const priya = {
    id: 21,
    name: 'Priya Shah',
    goal: 'launch' as const,
    assigned: true,
    signal: 'quiet' as const,
    last_active: null,
    next_session: null,
};

export const mentorToday = (
    over: Partial<MentorTodayData> = {},
): MentorTodayData => ({
    brokerage: 'Lone Star Realty',
    mentees: 2,
    sessions_this_week: 1,
    needs: [priya],
    upcoming: [
        {
            uuid: 'b-2',
            agent: 'Tom Reyes',
            agent_id: 22,
            starts_at: '2026-11-24T17:00:00Z',
            minutes: 30,
            topic: 'Pricing my first listing',
            status: 'confirmed',
            session: null,
            summary: null,
            tasks_sent: false,
        },
    ],
    wrap_ups: [],
    ...over,
});

export const menteeDetail = (
    over: Partial<MenteeDetailData> = {},
): MenteeDetailData => ({
    mentee: priya,
    plan: { week: 2, weeks: 12, done: 3, total: 20 },
    scorecard: [
        { key: 'conversations', done: 4, target: 10 },
        { key: 'appointments', done: 1, target: 2 },
    ],
    fix: 'conversations',
    deals: { active: 1, under_contract: 0, closed: 0 },
    sessions: [],
    goals: [
        {
            id: 5,
            title: 'Host an open house',
            due_date: '2026-11-29',
            done: false,
        },
    ],
    notes: [
        {
            id: 'n-1',
            body: 'Nervous about her first open house.',
            created_at: '2026-11-23T15:00:00Z',
        },
    ],
    ...over,
});
