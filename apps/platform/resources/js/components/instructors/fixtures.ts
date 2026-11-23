import type { InstructorRow } from './types';

/** Texas Realty School's instructors, as on screen 52. */
export const instructors: InstructorRow[] = [
    {
        id: 7,
        name: 'Dr. Rachel Hughes',
        email: 'rachel.hughes@texasrealty.example',
        status: 'active',
        courses: [{ id: 10, title: 'Principles of Real Estate II' }],
        approvals: 1,
        avatar: {
            status: 'given',
            given_at: '2027-02-12T15:00:00Z',
            lessons_due_by: null,
            twins: { heygen: null, synthesia: null },
        },
        rating: null,
        price: null,
    },
    {
        id: 8,
        name: 'Marcus Lee',
        email: 'marcus.lee@texasrealty.example',
        status: 'active',
        courses: [{ id: 11, title: 'Contracts for new agents' }],
        approvals: 1,
        avatar: {
            status: 'withdrawn',
            given_at: '2027-01-05T15:00:00Z',
            lessons_due_by: '2027-03-31',
            twins: { heygen: null, synthesia: null },
        },
        rating: null,
        price: null,
    },
    {
        id: -3,
        name: 'Tanya Brooks',
        email: 'tanya@example.com',
        status: 'invited',
        courses: [],
        approvals: 0,
        avatar: {
            status: null,
            given_at: null,
            lessons_due_by: null,
            twins: { heygen: null, synthesia: null },
        },
        rating: null,
        price: null,
    },
];

export const schoolCourses = [
    { id: 10, title: 'Principles of Real Estate II' },
    { id: 11, title: 'Contracts for new agents' },
];
