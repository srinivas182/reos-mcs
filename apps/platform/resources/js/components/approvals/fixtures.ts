import type { ApprovalOptions, ApprovalRow, ApprovalSummary } from './types';

const seat = {
    max_hours_per_day: 12,
    checkin_minutes: 15,
    presence_percent: 100,
    makeup_min_percent: 67,
    makeup_days: 90,
};

/** Texas Realty School's approvals, as on screen 50 in the journey. */
export const approvals: ApprovalRow[] = [
    {
        id: 1,
        course: {
            id: 10,
            title: 'Principles of Real Estate II',
            type: 'qualifying',
            delivery: 'self_paced',
        },
        state_code: 'TX',
        delivery: 'self_paced',
        number: 'TREC 0451',
        hours: 30,
        approved_on: '2024-09-01',
        expires_on: '2028-08-31',
        days_left: 640,
        status: 'approved',
        shown_status: 'approved',
        labels_course: true,
        records_live: false,
        note: null,
        ...seat,
    },
    {
        id: 2,
        course: {
            id: 10,
            title: 'Principles of Real Estate II',
            type: 'qualifying',
            delivery: 'self_paced',
        },
        state_code: 'TX',
        delivery: 'ai_avatar',
        number: null,
        hours: 30,
        approved_on: null,
        expires_on: null,
        days_left: null,
        status: 'pending',
        shown_status: 'pending',
        labels_course: false,
        records_live: false,
        note: 'Filed with TREC on Feb 2, 2027.',
        ...seat,
    },
    {
        id: 3,
        course: {
            id: 11,
            title: 'Contracts for new agents',
            type: 'ce',
            delivery: 'self_paced',
        },
        state_code: 'TX',
        delivery: 'self_paced',
        number: 'TREC CE 30117',
        hours: 4,
        approved_on: '2024-12-15',
        expires_on: '2027-03-26',
        days_left: 25,
        status: 'approved',
        shown_status: 'approved',
        labels_course: true,
        records_live: false,
        note: null,
        ...seat,
    },
];

export const options: ApprovalOptions = {
    courses: [
        {
            id: 10,
            title: 'Principles of Real Estate II',
            type: 'qualifying',
            delivery: 'self_paced',
            hours: 30,
            states: ['TX'],
            needs_approval: true,
        },
        {
            id: 11,
            title: 'Contracts for new agents',
            type: 'ce',
            delivery: 'self_paced',
            hours: 4,
            states: ['TX'],
            needs_approval: true,
        },
    ],
    states: [
        { code: 'OK', name: 'Oklahoma' },
        { code: 'TX', name: 'Texas' },
    ],
    deliveries: ['classroom', 'live_online', 'self_paced', 'ai_avatar'],
    defaults: { TX: seat },
};

export const summary: ApprovalSummary = {
    courses: 2,
    in_force: 2,
    attention: [
        {
            id: 3,
            course: 'Contracts for new agents',
            state_code: 'TX',
            number: 'TREC CE 30117',
            expires_on: '2027-03-26',
            days_left: 25,
            status: 'approved',
        },
        {
            id: 2,
            course: 'Principles of Real Estate II',
            state_code: 'TX',
            number: null,
            expires_on: null,
            days_left: null,
            status: 'pending',
        },
    ],
};
