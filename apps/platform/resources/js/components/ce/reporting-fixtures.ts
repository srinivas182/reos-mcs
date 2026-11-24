import type { Reporting } from './reporting-types';

/** Texas Realty School: two ready (one without a license on file), one rejected, one report pending upload and one reported. */
export function reporting(overrides: Partial<Reporting> = {}): Reporting {
    return {
        ready: [
            {
                completion: 11,
                student: 'James Miller',
                licence: '0798765',
                course: 'Legal Update I',
                course_number: '40123',
                hours: 4,
                completed_on: '2026-11-10',
                problem: null,
                rejected: null,
            },
            {
                completion: 12,
                student: 'Dana Brooks',
                licence: null,
                course: 'Legal Update I',
                course_number: '40123',
                hours: 4,
                completed_on: '2026-11-11',
                problem: 'no_licence',
                rejected: null,
            },
        ],
        rejected: [
            {
                completion: 9,
                student: 'Maria Lopez',
                licence: '0722222',
                course: 'Contracts',
                course_number: '40188',
                hours: 3,
                completed_on: '2026-10-20',
                problem: null,
                rejected: 'License number not found',
            },
        ],
        reports: [
            {
                id: 'a1b2c3d4-0000-7000-8000-000000000001',
                from_on: '2026-11-01',
                to_on: '2026-11-15',
                method: 'file',
                status: 'pending_upload',
                reference: null,
                count: 1,
                reported: 0,
                rejected: 0,
                created_by: 'Paul Diaz',
                created_at: '2026-11-16T15:00:00Z',
                lines: [
                    {
                        completion: 8,
                        student: 'Ava Thompson',
                        course: 'Legal Update II',
                        status: 'included',
                        reason: null,
                    },
                ],
            },
            {
                id: 'a1b2c3d4-0000-7000-8000-000000000002',
                from_on: '2026-10-01',
                to_on: '2026-10-31',
                method: 'reos',
                status: 'reported',
                reference: 'CE-2026-118844',
                count: 2,
                reported: 1,
                rejected: 1,
                created_by: 'Paul Diaz',
                created_at: '2026-11-01T15:00:00Z',
                lines: [
                    {
                        completion: 7,
                        student: 'Noah Patel',
                        course: 'Contracts',
                        status: 'reported',
                        reason: null,
                    },
                    {
                        completion: 9,
                        student: 'Maria Lopez',
                        course: 'Contracts',
                        status: 'rejected',
                        reason: 'License number not found',
                    },
                ],
            },
        ],
        reos: 'simulated',
        ...overrides,
    };
}
