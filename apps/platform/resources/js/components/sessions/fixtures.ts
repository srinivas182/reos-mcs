import type { Contact, Session } from '@/components/sessions/types';

/** Dr. Rachel Hughes (7) and her candidate Sofia Reyes (2) at Texas Realty School. */
export const session = (over: Partial<Session> = {}): Session => ({
    id: 's-1',
    kind: 'one_on_one',
    title: 'Contract review: TREC One to Four',
    agenda: 'Paragraphs 5 and 6 (earnest money and option fee)',
    starts_at: '2026-11-24T21:00:00Z',
    ends_at: '2026-11-24T21:45:00Z',
    join_opens_at: '2026-11-24T20:55:00Z',
    status: 'scheduled',
    host: { id: 7, name: 'Dr. Rachel Hughes' },
    people: [
        { id: 7, name: 'Dr. Rachel Hughes', role: 'host', joined: false },
        { id: 2, name: 'Sofia Reyes', role: 'guest', joined: false },
    ],
    me: 'host',
    can_join: false,
    notes: null,
    notes_updated_at: null,
    notes_updated_by: null,
    whiteboard: { image_url: null, updated_at: null },
    shares: [],
    recordings: [],
    ...over,
});

export const contacts: Contact[] = [
    {
        id: 2,
        name: 'Sofia Reyes',
        role: 'candidate',
        organisation: 'Texas Realty School',
    },
    {
        id: 3,
        name: 'Paul Diaz',
        role: 'school_admin',
        organisation: 'Texas Realty School',
    },
];
