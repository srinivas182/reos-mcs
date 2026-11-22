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
