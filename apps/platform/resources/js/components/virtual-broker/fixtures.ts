import type { BrokerQuestion, BrokerVirtualBroker } from './types';

/** Realistic REOS content for the virtual broker's stories and tests. */
export const question = (
    over: Partial<BrokerQuestion> = {},
): BrokerQuestion => ({
    id: '0193f1c2-7a10-7d43-9b1e-2f6a4c1d8e01',
    question: 'How many days do I have to deposit the earnest money?',
    answer: "The policy manual and TREC's rule disagree here, and TREC's rule wins.\n\nUnder 22 TAC §535.146: Trust money a license holder receives goes into the broker's trust account, or to the escrow agent named in the contract, no later than the close of business on the second working day after all parties have signed.",
    confidence: 'high',
    sources: [
        {
            kind: 'rule',
            title: '22 TAC §535.146',
            citation: '22 TAC §535.146',
            page: null,
            url: 'https://www.law.cornell.edu/regulations/texas/22-Tex-Admin-Code-SS-535-146',
            effective_from: '2026-11-24',
            excerpt:
                "Trust money a license holder receives (earnest money, option fees and similar deposits) goes into the broker's trust account, or to the escrow agent named in the contract, no later than the close of business on the second working day after all parties have signed the contract.",
        },
        {
            kind: 'manual',
            title: '6. Earnest money',
            citation: null,
            page: 12,
            url: null,
            effective_from: '2026-11-20',
            excerpt:
                'Earnest money checks go to the title company named in the contract within three business days.',
        },
    ],
    status: 'waiting',
    reason: 'money',
    escalated_at: '2026-11-24T15:00:00Z',
    due_at: '2026-11-24T19:00:00Z',
    broker_answer: null,
    broker: null,
    answered_at: null,
    added_to_knowledge: false,
    agent: 'James Miller',
    deal: {
        id: 7,
        uuid: '0193f1c2-7a10-7d43-9b1e-2f6a4c1d8e77',
        property: '1420 Pecan St, Austin',
    },
    created_at: '2026-11-24T15:00:00Z',
    ...over,
});

export const brokerData = (
    over: Partial<BrokerVirtualBroker> = {},
): BrokerVirtualBroker => ({
    brokerage: 'Lone Star Realty',
    manual: {
        id: '0193f1c2-7a10-7d43-9b1e-2f6a4c1d8e10',
        version: 3,
        file_name: 'Lone Star policy manual 2026.pdf',
        pages: 84,
        passages: 212,
        topics: ['advertising', 'splits', 'escrow', 'coordinators'],
        uploaded_at: '2026-11-20T14:00:00Z',
        uploaded_by: 'Linda Park',
    },
    versions: [
        {
            id: 'v3',
            version: 3,
            file_name: 'Lone Star policy manual 2026.pdf',
            uploaded_at: '2026-11-20T14:00:00Z',
            current: true,
        },
        {
            id: 'v2',
            version: 2,
            file_name: 'Lone Star policy manual 2025.pdf',
            uploaded_at: '2025-08-02T14:00:00Z',
            current: false,
        },
    ],
    conflicts: [
        {
            rule: 'brokerage.trust_money',
            citation: '22 TAC §535.146',
            url: 'https://www.law.cornell.edu/regulations/texas/22-Tex-Admin-Code-SS-535-146',
            page: 12,
            excerpt:
                'Earnest money checks go to the title company named in the contract within three business days.',
            rule_text:
                'Trust money goes into the trust account or to the escrow agent no later than the second working day after all parties have signed.',
        },
    ],
    faq: [
        {
            id: 4,
            question: 'Who approves yard sign designs?',
            answer: 'Send yard sign designs to Linda for approval; signs use the office template.',
            added_at: '2026-11-21T10:00:00Z',
        },
    ],
    settings: {
        answerer_id: null,
        reply_hours: 4,
        brokers: [
            { id: 1, name: 'Linda Park' },
            { id: 2, name: 'Dana Cole' },
        ],
    },
    waiting: [question()],
    answered: [
        question({
            id: 'a-1',
            question: 'Who approves my yard sign?',
            status: 'corrected',
            reason: 'unclear',
            broker: 'Linda Park',
            broker_answer: 'Send it to me; signs use the office template.',
            deal: null,
        }),
    ],
    stats: { answered_by_ai: 41, sent_to_broker: 6, reply_minutes: 52 },
    ...over,
});
