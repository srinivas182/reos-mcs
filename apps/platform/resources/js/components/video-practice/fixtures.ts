import type { VideoOverview, VideoReview } from './types';

/** Realistic video practice data for stories and tests (Maria Garcia, Katy). */
export const reviewFixture = (
    over: Partial<VideoReview> = {},
): VideoReview => ({
    id: '0193a1d2-0000-7000-8000-0000000000b1',
    kind: 'listing_pitch',
    transcript:
        'Hi, I am Maria Garcia with Lone Star Realty. Um, I help families in Katy sell their homes. This year I sold fourteen homes in Cinco Ranch, basically most in under twenty days.',
    duration_seconds: 42,
    words: 118,
    wpm: 169,
    pace: 'fast',
    fillers: [
        { word: 'um', count: 3 },
        { word: 'basically', count: 1 },
    ],
    score: 66,
    scores: { pace: 14, fillers: 14, opening: 20, proof: 18, close: 0 },
    fixes: [
        {
            dimension: 'close',
            text: 'End with one clear next step.',
            example:
                "Text me today and I'll send you a free price review this week.",
            lesson: 'asking_for_business',
        },
        {
            dimension: 'pace',
            text: 'Speak at an easy pace: about 130 to 160 words a minute, with short pauses.',
            example:
                "I'll keep this short. (pause) Here's what I'll do for you.",
            lesson: 'clear_speaking',
        },
        {
            dimension: 'fillers',
            text: 'Replace filler words with a short pause.',
            example: "Here's my plan. (pause) First, pricing.",
            lesson: 'clear_speaking',
        },
    ],
    compliance: [],
    engine: 'script',
    created_at: '2026-11-28T09:00:00-06:00',
    ...over,
});

export const overviewFixture = (
    over: Partial<VideoOverview> = {},
): VideoOverview => ({
    kinds: ['listing_pitch', 'video_intro'],
    max_seconds: 120,
    recent: [
        {
            id: '0193a1d2-0000-7000-8000-0000000000b1',
            kind: 'listing_pitch',
            score: 66,
            wpm: 169,
            duration_seconds: 42,
            created_at: '2026-11-28T09:00:00-06:00',
        },
    ],
    ...over,
});
