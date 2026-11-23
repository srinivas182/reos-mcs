import type { AskThread } from './types';

/** Ana's question about the IABS, answered from her Texas course. */
export const iabsThread: AskThread = {
    uuid: '6f1e2d3c-4b5a-4c6d-8e7f-9a0b1c2d3e4f',
    title: 'When is the IABS form given?',
    updated_at: '2026-11-23T15:00:00Z',
    messages: [
        {
            id: 1,
            role: 'question',
            body: 'When is the IABS form given?',
            tier: null,
            mode: null,
            sources: [],
            quiz: null,
            math_kind: null,
            sponsor: null,
            created_at: '2026-11-23T15:00:00Z',
        },
        {
            id: 2,
            role: 'answer',
            body: 'At the first substantive contact with a prospective client. It explains who the license holder represents.',
            tier: 'content',
            mode: 'ask',
            sources: [
                {
                    lesson_id: 7,
                    lesson: 'Agency basics',
                    seconds: 200,
                    page: null,
                    heading: 'The IABS form',
                    text: 'A license holder gives the IABS form at the first substantive contact.',
                },
            ],
            quiz: null,
            math_kind: null,
            sponsor: null,
            created_at: '2026-11-23T15:00:02Z',
        },
    ],
};

/** A question beyond the Texas course, answered because her school sponsors extra help. */
export const sponsoredAnswer = {
    ...iabsThread.messages[1]!,
    id: 4,
    body: 'Louisiana assesses residential property at 10% of fair market value. That goes beyond your Texas course.',
    tier: 'sponsored' as const,
    sources: [],
    sponsor: 'Lone Star School of Real Estate',
};

/** The same, with no sponsor: the course doesn't cover it. */
export const limitedAnswer = {
    ...sponsoredAnswer,
    id: 5,
    body: "That isn't covered in your Texas course content, so I can't answer it from there. Your instructor can: ask them, or book a short session.",
    tier: 'limited' as const,
    sponsor: null,
};
