import type { LicenceReviewItem } from '@/components/licence-reviews';
import type { Licence, LicenceState } from '@/types';

/** James Miller's Texas licence, checked with TREC (journey screen 148). */
export const texasLicence: Licence = {
    id: 1,
    state: { code: 'TX', name: 'Texas', live: true },
    type: 'sales_agent',
    number: '0798765',
    status: 'active',
    broker_role: null,
    holder_name: 'James Miller',
    licensed_on: '2024-04-01',
    expires_on: '2027-03-31',
    sponsor_name: 'Lone Star Realty',
    first_renewal: false,
    verified_by: 'trec-licence',
    verified_at: '2026-11-21T16:00:00+00:00',
    review_note: null,
    tracked_only: false,
};

/** His Louisiana licence: REOS isn't live there yet, so it's tracked for renewal. */
export const louisianaLicence: Licence = {
    ...texasLicence,
    id: 2,
    state: { code: 'LA', name: 'Louisiana', live: false },
    number: '0995-21837',
    status: 'pending_review',
    holder_name: null,
    expires_on: null,
    sponsor_name: null,
    verified_by: null,
    verified_at: null,
    review_note: "This state's records can't be checked automatically yet.",
    tracked_only: true,
};

export const licenceStates: LicenceState[] = [
    { code: 'TX', name: 'Texas', live: true },
    { code: 'FL', name: 'Florida', live: false },
    { code: 'LA', name: 'Louisiana', live: false },
];

export const licenceReviews: LicenceReviewItem[] = [
    {
        id: 2,
        person: { id: 41, name: 'James Miller' },
        state: 'Louisiana',
        number: '0995-21837',
        note: "This state's records can't be checked automatically yet.",
        created_at: '2026-11-21T16:00:00+00:00',
    },
    {
        id: 3,
        person: { id: 42, name: 'Maria Lopez' },
        state: 'Texas',
        number: '0798888',
        note: "The regulator's record is under another name: Pat Jordan.",
        created_at: '2026-11-21T16:05:00+00:00',
    },
];
