import type { LicenceReviewItem } from '@/components/licence-reviews';
import type { CeRequirement, Licence, LicenceState } from '@/types';

/** James Miller's Texas CE: 9 of 18 hours, Legal Update II still to do (journey screen 148). */
export const texasCe: CeRequirement = {
    state: 'TX',
    due_on: '2027-03-31',
    period_start: '2025-03-31',
    days_left: 130,
    first_renewal: false,
    hours_required: 18,
    hours_done: 9,
    hours_left: 9,
    complete: false,
    parts: [
        {
            code: 'legal_update_1',
            title: 'Legal Update I: Laws, Rules and Forms',
            kind: 'mandatory',
            hours: 4,
            hours_done: 4,
            done: true,
        },
        {
            code: 'legal_update_2',
            title: 'Legal Update II: Agency, Ethics and Hot Topics',
            kind: 'mandatory',
            hours: 4,
            hours_done: 0,
            done: false,
        },
        {
            code: 'contracts',
            title: 'Real estate contracts',
            kind: 'mandatory',
            hours: 3,
            hours_done: 3,
            done: true,
        },
        {
            code: 'electives',
            title: 'Electives',
            kind: 'elective',
            hours: 7,
            hours_done: 2,
            done: false,
        },
    ],
    deferral: { fee_usd: 200, days: 60 },
    sources: [
        {
            key: 'ce.hours',
            citation: '22 TAC §535.92(a)',
            url: 'https://www.law.cornell.edu/regulations/texas/22-Tex-Admin-Code-SS-535-92',
            effective_from: '2023-08-29',
        },
        {
            key: 'ce.deferral',
            citation: 'TREC renewal requirements: CE deferral fee',
            url: null,
            effective_from: '2023-08-29',
        },
    ],
};

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
    ce: texasCe,
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
    ce: null,
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
