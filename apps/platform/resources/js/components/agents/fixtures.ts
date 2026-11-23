import type { AgentPlan, AgentTodayData, ModeState, Scorecard } from './types';

/** James Miller, licensed in 2023, Launch mode with his first verified closing. */
export function modeState(overrides: Partial<ModeState> = {}): ModeState {
    return {
        goal: 'launch',
        flags: ['bilingual'],
        needs_setup: false,
        suggested_default: 'launch',
        own: { unlocked: false, unlocks_on: '2027-04-01', years: 4 },
        suggestion: null,
        history: [
            {
                from: null,
                to: 'launch',
                reason: 'setup',
                at: '2026-11-20T15:00:00Z',
            },
        ],
        welcome: {
            name: 'James Miller',
            licence: '0798765',
            state: 'TX',
            brokerage: 'Linda Park',
            verified: true,
        },
        ...overrides,
    };
}

/** James's Today in week 3 of Launch: an overdue follow-up, a deadline, a showing and plan tasks. */
export function agentToday(
    overrides: Partial<AgentTodayData> = {},
): AgentTodayData {
    return {
        goal: 'launch',
        stats: [
            { key: 'week', value: 3, target: 13 },
            { key: 'conversations', value: 12, target: 20 },
            { key: 'appointments', value: 1, target: 2 },
        ],
        items: [
            {
                key: 'todo-1',
                kind: 'todo',
                reason: 'overdue',
                title: 'Call Maria Lopez back about pre-approval',
                detail: 'Maria Lopez',
                source: 'Follow Up Boss',
                at: '2026-11-23T00:00:00-06:00',
                all_day: true,
                todo_id: 1,
                deal: null,
                plan: null,
            },
            {
                key: 'todo-2',
                kind: 'deadline',
                reason: 'deadline',
                title: 'Option period ends',
                detail: '1420 Pecan St, Austin',
                source: 'dotloop',
                at: '2026-11-25T17:00:00-06:00',
                all_day: false,
                todo_id: 2,
                deal: 'a1b2',
                plan: null,
            },
            {
                key: 'event-7',
                kind: 'event',
                reason: 'appointment',
                title: 'Showing with the Nguyens',
                detail: '88 Oak Hollow Dr',
                source: 'Google Calendar',
                at: '2026-11-24T15:30:00-06:00',
                all_day: false,
                todo_id: null,
                deal: null,
                plan: null,
            },
            {
                key: 'plan-call_sphere-3',
                kind: 'plan',
                reason: 'plan',
                title: 'Call 10 people you know',
                detail: null,
                source: '90-day plan',
                at: null,
                all_day: true,
                todo_id: null,
                deal: null,
                plan: { code: 'call_sphere', week: 3 },
            },
        ],
        more: 2,
        day_one: null,
        plan_week: 3,
        ...overrides,
    };
}

/** James's plan: Launch, week 3, in the Conversations phase. */
export function agentPlan(overrides: Partial<AgentPlan> = {}): AgentPlan {
    return {
        goal: 'launch',
        starts_on: '2026-11-10',
        ends_on: '2027-02-09',
        week: 3,
        weeks: 13,
        phases: [
            { code: 'setup', from: 1, to: 2, current: false },
            { code: 'conversations', from: 3, to: 6, current: true },
            { code: 'appointments', from: 7, to: 10, current: false },
            { code: 'contract', from: 11, to: 13, current: false },
        ],
        targets: {
            conversations: 20,
            appointments: 2,
            clients: 1,
            ce_hours: 1,
        },
        tasks: [
            {
                code: 'call_sphere',
                week: 3,
                done: false,
                auto: false,
                repeats: true,
                link: 'contacts',
            },
            {
                code: 'ask_referrals',
                week: 3,
                done: true,
                auto: false,
                repeats: false,
                link: 'contacts',
            },
            {
                code: 'mentor_checkin',
                week: 3,
                done: false,
                auto: false,
                repeats: true,
                link: 'mentors',
            },
        ],
        done: 5,
        total: 52,
        promised: false,
        ...overrides,
    };
}

/** James's scorecard: conversations behind. */
export function scorecard(overrides: Partial<Scorecard> = {}): Scorecard {
    return {
        week_of: '2026-11-23',
        metrics: [
            { key: 'conversations', done: 6, target: 20 },
            { key: 'appointments', done: 1, target: 2 },
            { key: 'clients', done: 1, target: 1 },
            { key: 'ce_hours', done: 1, target: 1 },
        ],
        fix: 'conversations',
        ...overrides,
    };
}
