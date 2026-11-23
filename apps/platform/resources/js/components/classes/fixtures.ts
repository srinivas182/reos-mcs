import type {
    ClassCourse,
    ClassLive,
    LiveClassRow,
} from '@/components/classes/types';

/** Realistic REOS content for stories and tests (A10.2b). */
export const classRow = (over: Partial<LiveClassRow> = {}): LiveClassRow => ({
    id: '6f9a1c2e-4b7d-4e1a-9c3f-2a8b5d6e7f01',
    title: 'Contract update: live review',
    agenda: 'The 2026 TREC amendments, line by line.',
    course: { id: 12, title: 'Texas Contract Update' },
    host: 'Nelle Hart',
    starts_at: '2026-11-24T16:30:00Z',
    ends_at: '2026-11-24T18:30:00Z',
    minutes: 120,
    status: 'scheduled',
    ce: true,
    ce_hours: 2,
    checkin_minutes: 15,
    registered: 18,
    capacity: 30,
    me: null,
    can_register: true,
    full: false,
    can_join: false,
    ...over,
});

export const classCourses: ClassCourse[] = [
    { id: 12, title: 'Texas Contract Update', ce: true },
    { id: 14, title: 'Principles of Real Estate I', ce: false },
];

export const classLive = (over: Partial<ClassLive> = {}): ClassLive => ({
    ce: true,
    viewers: 17,
    me: { role: 'guest', hand: false, on_stage: false },
    hands: [],
    polls: [
        {
            id: 3,
            question: 'Who initials the amendment?',
            options: [
                { label: 'The buyer', votes: 4 },
                { label: 'The seller', votes: 2 },
                { label: 'Both parties', votes: 11 },
            ],
            total: 17,
            my_vote: 2,
            closed: false,
        },
    ],
    questions: [
        {
            id: 41,
            body: 'Does the old form stay valid until January?',
            by: 'Ben Ortiz',
            likes: 6,
            liked: true,
            mine: false,
            answered: false,
        },
        {
            id: 42,
            body: 'Who keeps the original after closing?',
            by: 'Ana Ruiz',
            likes: 2,
            liked: false,
            mine: true,
            answered: false,
        },
    ],
    messages: [
        {
            id: 7,
            by: 'Marco Diaz',
            body: 'Can you share the slide with the new box 12?',
            at: '2026-11-24T16:52:00Z',
            mine: false,
        },
        {
            id: 8,
            by: 'Ana Ruiz',
            body: 'Thank you, that helps.',
            at: '2026-11-24T16:53:00Z',
            mine: true,
        },
    ],
    check: null,
    checks: [],
    next_check_at: null,
    ...over,
});
