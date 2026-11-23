/** A video session as the server presents it (B6.3, SessionPresenter). */
export type SessionPerson = {
    id: number;
    name: string;
    role: 'host' | 'guest';
    joined: boolean;
};

export type Session = {
    id: string;
    kind: 'one_on_one' | 'live_class';
    title: string;
    agenda: string | null;
    starts_at: string;
    ends_at: string;
    join_opens_at: string;
    status: 'scheduled' | 'live' | 'ended' | 'cancelled';
    host: { id: number; name: string };
    people: SessionPerson[];
    me: 'host' | 'guest';
    can_join: boolean;
    notes: string | null;
    notes_updated_at: string | null;
    notes_updated_by: string | null;
    /** The shared whiteboard's picture (B6.3b). */
    whiteboard: { image_url: string | null; updated_at: string | null };
    /** Lessons and practice questions the host shared (B6.3b). */
    shares: Share[];
    /** Recordings (B6.3c), latest first. */
    recordings: Recording[];
    /** A live class (A10.2b): its course, CE and registrations. */
    class?: {
        course: string;
        ce: boolean;
        registered: number;
        capacity: number;
    } | null;
};

export type Recording = {
    id: string;
    status:
        | 'asking'
        | 'declined'
        | 'recording'
        | 'stopping'
        | 'ready'
        | 'failed';
    requested_by: string | null;
    started_at: string | null;
    stopped_at: string | null;
    duration_seconds: number | null;
    expires_at: string | null;
    play_url: string | null;
    agreed: string[];
    waiting: string[];
    my_answer: boolean | null;
};

export type PracticeQuestion = {
    id: number;
    question: string;
    options: string[];
    correct: number;
    explanation: string;
    source: string;
};

export type Share = {
    id: number;
    kind: 'lesson' | 'questions';
    at: string;
    by: string | null;
    lesson: { id: number; title: string; course: string } | null;
    topic: string | null;
    questions: PracticeQuestion[];
};

/** What Join returns: the room server and token, or a simulated room. */
export type RoomConnection = {
    simulated: boolean;
    url: string | null;
    token: string | null;
    ends_at: string;
};

export type Contact = {
    id: number;
    name: string;
    role: string;
    organisation: string;
};

/** The others in the session, as "Linda Park" or "Linda Park and 3 others". */
export function withWhom(session: Session, me: number): string[] {
    return session.people.filter((p) => p.id !== me).map((p) => p.name);
}
