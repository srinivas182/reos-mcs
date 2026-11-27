import type { Assessment, Overview, Session } from './types';

/** Realistic role-play data for stories and tests (Maria Garcia, Katy, Texas). */
export const overviewFixture = (over: Partial<Overview> = {}): Overview => ({
    types: [
        {
            type: 'listing',
            personas: ['interviewing_seller', 'downsizing_couple'],
            best: 84,
        },
        {
            type: 'objection',
            personas: ['rate_worried_buyer', 'commission_pushback'],
            best: null,
        },
        { type: 'prospecting', personas: ['fsbo', 'expired'], best: 61 },
        { type: 'appraisal_gap', personas: ['low_appraisal'], best: null },
        { type: 'follow_up', personas: ['cold_lead'], best: null },
    ],
    recent: [
        {
            id: '0193a1d2-0000-7000-8000-000000000001',
            type: 'listing',
            persona: 'interviewing_seller',
            kind: 'practice',
            status: 'scored',
            score: 84,
            created_at: '2026-11-26T15:00:00-06:00',
        },
        {
            id: '0193a1d2-0000-7000-8000-000000000002',
            type: 'prospecting',
            persona: 'expired',
            kind: 'practice',
            status: 'active',
            score: null,
            created_at: '2026-11-27T09:30:00-06:00',
        },
    ],
    assessment: assessmentFixture(),
    ...over,
});

export const assessmentFixture = (
    over: Partial<Assessment> = {},
): Assessment => ({
    id: '0193a1d2-0000-7000-8000-0000000000a1',
    done: 2,
    total: 5,
    next: 'prospecting',
    completed: false,
    results: [],
    weakest: [],
    completed_at: null,
    ...over,
});

export const activeSession = (over: Partial<Session> = {}): Session => ({
    id: '0193a1d2-0000-7000-8000-000000000003',
    type: 'listing',
    persona: 'interviewing_seller',
    kind: 'practice',
    round: null,
    status: 'active',
    turns: [
        {
            role: 'persona',
            text: "Thanks for coming. I'm talking to three agents this week, so tell me: why should I list with you?",
        },
        {
            role: 'agent',
            text: 'Thanks for having me, Mark. I sold 14 homes in Cinco Ranch this year, most in under 20 days.',
        },
        {
            role: 'persona',
            text: 'Another agent told me we could get 30,000 dollars more than your number. Why is your price lower?',
        },
    ],
    turns_left: 3,
    score: null,
    scores: null,
    fixes: [],
    outcome: null,
    engine: 'script',
    created_at: '2026-11-27T10:00:00-06:00',
    ...over,
});

export const scoredSession = (over: Partial<Session> = {}): Session =>
    activeSession({
        status: 'scored',
        turns_left: 0,
        score: 72,
        scores: {
            structure: 20,
            questions: 10,
            objections: 16,
            clarity: 16,
            ask: 10,
        },
        outcome: 'won',
        fixes: [
            {
                dimension: 'questions',
                text: 'Ask before you tell: find out what matters most to them.',
                example:
                    'Before I share my plan, what matters most to you about this move?',
                lesson: 'discovery_questions',
            },
            {
                dimension: 'ask',
                text: 'End with a clear, specific next step.',
                example: 'Can we meet Thursday at 4 to go over the details?',
                lesson: 'asking_for_business',
            },
            {
                dimension: 'clarity',
                text: 'Keep each answer short and plain, and drop the fillers.',
                example:
                    "Here's the short version: three steps, and I'll handle each one.",
                lesson: 'clear_speaking',
            },
        ],
        ...over,
    });
