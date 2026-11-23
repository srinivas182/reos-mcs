import type { OfferRow } from '@/components/licence/types';

/** A candidate who asked for broker offers, as a brokerage sees them (A10.3b). */
export type RecruitCandidate = {
    id: number;
    first_name: string;
    city: string | null;
    stage: 'studying' | 'exam_booked' | 'passed';
    offer: OfferRow | null;
};
