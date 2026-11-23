/** Live classes (A10.2b) as the server presents them (LiveClasses, ClassRoom). */
export type LiveClassRow = {
    id: string;
    title: string;
    agenda: string | null;
    course: { id: number; title: string };
    host: string;
    starts_at: string;
    ends_at: string;
    minutes: number;
    status: 'scheduled' | 'live' | 'ended' | 'cancelled';
    /** A CE class: attendance is checked and reported. */
    ce: boolean;
    ce_hours: number | null;
    checkin_minutes: number | null;
    registered: number;
    capacity: number;
    me: 'host' | 'registered' | null;
    can_register: boolean;
    full: boolean;
    can_join: boolean;
};

export type ClassCourse = { id: number; title: string; ce: boolean };

export type ClassPoll = {
    id: number;
    question: string;
    /** Votes are null until the student answers or the poll closes. */
    options: { label: string; votes: number | null }[];
    total: number | null;
    my_vote: number | null;
    closed: boolean;
};

export type ClassQuestion = {
    id: number;
    body: string;
    by: string;
    likes: number;
    liked: boolean;
    mine: boolean;
    answered: boolean;
};

export type ClassLive = {
    ce: boolean;
    viewers: number;
    me: { role: 'host' | 'guest'; hand: boolean; on_stage: boolean };
    hands: {
        id: number;
        name: string;
        since: string | null;
        on_stage: boolean;
    }[];
    polls: ClassPoll[];
    questions: ClassQuestion[];
    messages: {
        id: number;
        by: string;
        body: string;
        at: string;
        mine: boolean;
    }[];
    /** The open attendance check (students). */
    check: { id: number; closes_at: string; answered: boolean } | null;
    /** Every check with how many answered (host). */
    checks: {
        id: number;
        opens_at: string;
        closes_at: string;
        answered: number;
        of: number;
    }[];
    next_check_at: string | null;
};
