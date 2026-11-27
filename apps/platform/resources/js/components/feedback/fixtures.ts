import type { FeedbackView, MyFeedback } from './types';

/** Priya Shah's feedback after closings with Lone Star Realty agents (REOS demo content). */
export const mineFixture = (over: Partial<MyFeedback> = {}): MyFeedback => ({
    count: 4,
    needed: 3,
    shown: true,
    average: {
        overall: 4.2,
        communication: 3.8,
        responsiveness: 4.0,
        professionalism: 4.8,
    },
    items: [
        {
            id: 'f1',
            month: '2026-11',
            communication: 3,
            responsiveness: 4,
            professionalism: 5,
            comment:
                'Slow to share the inspection report, but everything else was smooth.',
            response: null,
            reported: false,
        },
        {
            id: 'f2',
            month: '2026-10',
            communication: 4,
            responsiveness: 4,
            professionalism: 5,
            comment: 'Clear and on time.',
            response: 'Thank you, it was a pleasure.',
            reported: false,
        },
    ],
    tip: {
        dimension: 'communication',
        text: 'Agents would like to hear from you more. Send a short update at each step, even when there is no news.',
        engine: 'template',
    },
    public: false,
    ...over,
});

export const viewFixture = (
    over: Partial<FeedbackView> = {},
): FeedbackView => ({
    to_rate: [
        {
            deal: '1f2e3d4c-5b6a-4789-8a0b-1c2d3e4f5a6b',
            property: '88 Oak Ridge Dr, Katy',
            closed_at: '2026-11-24T18:00:00Z',
            agent: { id: 21, name: 'Priya Shah' },
        },
    ],
    mine: mineFixture(),
    ...over,
});
