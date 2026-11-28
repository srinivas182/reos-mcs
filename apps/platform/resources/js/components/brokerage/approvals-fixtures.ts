import type { ApprovalsQueueData } from './types';

/** Lone Star Realty's queue on a Monday morning (A15.3): a post past the limit, a page and a question. */
export const loneStarQueue: ApprovalsQueueData = {
    brokerage: 'Lone Star Realty',
    scope: 'brokerage',
    team: null,
    fallback_hours: 4,
    can: { posts: true, questions: true, settings: true },
    counts: { posts: 1, pages: 1, questions: 1, overdue: 1 },
    items: [
        {
            kind: 'post',
            id: '01936f0e-0000-7000-8000-000000000001',
            agent: 'James Miller',
            summary:
                'Open house Saturday at 12 Oak Lane, Katy. The best deal in town!',
            waiting_since: '2026-11-27T12:00:00-06:00',
            hours: 7,
            overdue: true,
            detail: {
                body: 'Open house Saturday at 12 Oak Lane, Katy. The best deal in town!',
                attribution: 'James Miller · Lone Star Realty',
                channels: ['facebook', 'instagram'],
                scheduled_at: null,
                issues: [
                    {
                        code: 'claims',
                        phrase: 'best deal in town',
                        fix: 'Say what makes it a good value instead.',
                    },
                ],
                reason: null,
            },
        },
        {
            kind: 'page',
            id: 'priya-shah',
            agent: 'Priya Shah',
            summary: 'Katy homes and first-time buyers.',
            waiting_since: '2026-11-30T07:00:00-06:00',
            hours: 1,
            overdue: false,
            detail: {
                bio: 'Katy homes and first-time buyers.',
                url: 'https://reos.com/agents/priya-shah',
            },
        },
        {
            kind: 'question',
            id: '01936f0e-0000-7000-8000-000000000003',
            agent: 'James Miller',
            summary: 'Can I pay a referral fee to my cousin?',
            waiting_since: '2026-11-30T08:55:00-06:00',
            hours: 0,
            overdue: false,
            detail: {
                question: 'Can I pay a referral fee to my cousin?',
                answer: 'Referral fees go through the brokerage. A license holder may not pay an unlicensed person for a referral.',
                reason: 'money',
                deal: null,
            },
        },
    ],
};
