/** Booking 1-on-1 sessions (A10.2a). */
export type InstructorOffer = {
    id: number;
    user_id: number;
    name: string;
    school: string | null;
    bio: string | null;
    languages: string[];
    topics: string[];
    /** Cents; 0 is included. */
    price_30: number;
    price_60: number;
    rating: number | null;
    ratings: number;
    next_slot: string | null;
};

export type Booking = {
    uuid: string;
    /** The viewer's side of it. */
    as: 'student' | 'instructor';
    instructor: string;
    student: string;
    starts_at: string;
    ends_at: string;
    minutes: number;
    topic: string;
    status:
        | 'held'
        | 'confirmed'
        | 'cancelled'
        | 'refunded'
        | 'completed'
        | 'no_show';
    price: number;
    session: string | null;
    can_cancel: boolean;
    refund_on_cancel: boolean;
    can_rate: boolean;
    rating: number | null;
    tasks_sent: boolean;
    checkout_url: string | null;
};

export type SessionOfferValues = {
    bio: string | null;
    languages: string[];
    topics: string[];
    hours: Record<string, [string, string][]>;
    timezone: string;
    price_30_cents: number;
    price_60_cents: number;
    active: boolean;
};

export const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as const;
