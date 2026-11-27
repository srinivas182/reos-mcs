/** Video practice reviews (A14.1b), as the page and API send them. */
import type { Lesson } from '@/components/role-play/types';

export type VideoKind = 'listing_pitch' | 'video_intro';

export type VideoDimension = 'pace' | 'fillers' | 'opening' | 'proof' | 'close';

export const VIDEO_DIMENSIONS: VideoDimension[] = [
    'pace',
    'fillers',
    'opening',
    'proof',
    'close',
];

export type ComplianceCode =
    | 'broker'
    | 'broker_size'
    | 'team_name'
    | 'links'
    | 'claims'
    | 'title'
    | 'fair_housing'
    | 'unclear'
    | 'banned'
    | 'unlicensed';

export type VideoReview = {
    id: string;
    kind: VideoKind;
    transcript: string;
    duration_seconds: number;
    words: number;
    wpm: number;
    pace: 'slow' | 'good' | 'fast';
    fillers: { word: string; count: number }[];
    score: number;
    scores: Record<VideoDimension, number>;
    fixes: {
        dimension: VideoDimension;
        text: string;
        example: string;
        lesson: Lesson;
    }[];
    compliance: { code: string; phrase: string | null }[];
    engine: 'claude' | 'script';
    created_at: string;
};

export type VideoSummary = {
    id: string;
    kind: VideoKind;
    score: number;
    wpm: number;
    duration_seconds: number;
    created_at: string;
};

export type VideoOverview = {
    kinds: VideoKind[];
    max_seconds: number;
    recent: VideoSummary[];
};
