import type { OfferRow } from '@/components/licence/types';

/** A candidate who asked for broker offers, as a brokerage sees them (A10.3b). */
export type RecruitCandidate = {
    id: number;
    first_name: string;
    city: string | null;
    stage: 'studying' | 'exam_booked' | 'passed';
    offer: OfferRow | null;
};

/** Sponsored AI help (A17.2, screen 26). */
export type SponsoredBoard = {
    pool: number;
    candidates: {
        id: string;
        candidate: string;
        cap: number;
        used: number;
        status: 'active' | 'used_up' | 'ended';
        end_reason: string | null;
        since: string;
    }[];
};

/** A licensed agent open to a move (A17.2, screen 27). */
export type OpenAgent = {
    id: number;
    name: string;
    closings: number;
    focus: string;
    reasons: ('closings' | 'city' | 'new')[];
    invited_at: string | null;
    conversation: string | null;
};
