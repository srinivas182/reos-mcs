import type { Booking, InstructorOffer } from './types';

/** Nelle Hart teaches contracts and math at Texas Realty School. */
export const nelle: InstructorOffer = {
    id: 1,
    user_id: 12,
    name: 'Nelle Hart',
    school: 'Texas Realty School',
    bio: 'Twenty years teaching Texas contracts and real estate math.',
    languages: ['en', 'es'],
    topics: ['Contracts', 'Math'],
    price_30: 3500,
    price_60: 6000,
    rating: 4.9,
    ratings: 38,
    next_slot: '2026-11-24T19:00:00Z',
};

export const anaBooking: Booking = {
    uuid: '5c4b3a29-1807-4f6e-9d5c-4b3a29180f6e',
    as: 'student',
    instructor: 'Nelle Hart',
    student: 'Ana Ruiz',
    starts_at: '2026-11-24T19:00:00Z',
    ends_at: '2026-11-24T19:30:00Z',
    minutes: 30,
    topic: 'Proration math',
    status: 'confirmed',
    price: 3500,
    session: '9a8b7c6d-5e4f-4a3b-8c2d-1e0f9a8b7c6d',
    can_cancel: true,
    refund_on_cancel: true,
    can_rate: false,
    rating: null,
    tasks_sent: false,
    checkout_url: null,
};
