import { describe, expect, it } from 'vitest';
import {
    blankForm,
    eventWhen,
    formFromEvent,
    isoToZoned,
    payload,
    zonedToIso,
} from '@/lib/calendar';
import { calendarEvent } from '@/components/calendar-fixtures';

const HOUSTON = 'America/Chicago';

describe('calendar times (B4.1)', () => {
    it('turns Houston wall time into an instant, across daylight saving', () => {
        expect(zonedToIso('2026-11-24', '14:30', HOUSTON)).toBe(
            '2026-11-24T20:30:00.000Z',
        );
        expect(zonedToIso('2026-07-01', '09:00', HOUSTON)).toBe(
            '2026-07-01T14:00:00.000Z',
        );
        expect(isoToZoned('2026-11-24T20:30:00Z', HOUSTON)).toEqual({
            date: '2026-11-24',
            time: '14:30',
        });
    });

    it('starts a new appointment at the next hour today, 10 AM on other days', () => {
        const now = new Date('2026-11-21T15:20:00Z'); // 9:20 AM in Houston

        expect(blankForm('2026-11-21', HOUSTON, now)).toMatchObject({
            start: '10:00',
            end: '11:00',
            kind: 'appointment',
            notify: false,
        });
        expect(blankForm('2026-11-23', HOUSTON, now).start).toBe('10:00');
    });

    it('round-trips an event through the form, and sends all-day events as dates', () => {
        const event = calendarEvent({
            starts_at: '2026-11-24T20:00:00+00:00',
            ends_at: '2026-11-24T20:45:00+00:00',
            attendees: ['garcia.family@example.com'],
        });
        const form = formFromEvent(event, HOUSTON);

        expect(form).toMatchObject({
            date: '2026-11-24',
            start: '14:00',
            end: '14:45',
            guests: 'garcia.family@example.com',
        });
        expect(payload(form, HOUSTON, true)).toMatchObject({
            kind: 'showing',
            all_day: false,
            starts_at: '2026-11-24T20:00:00.000Z',
            ends_at: '2026-11-24T20:45:00.000Z',
            attendees: ['garcia.family@example.com'],
            notify_guests: false,
        });
        expect(
            payload(
                { ...form, allDay: true, endDate: '2026-11-25' },
                HOUSTON,
                false,
            ),
        ).toMatchObject({
            all_day: true,
            start_date: '2026-11-24',
            end_date: '2026-11-25',
        });
    });

    it('reads an end before the start as after midnight', () => {
        const form = {
            ...blankForm('2026-11-24', HOUSTON),
            start: '23:00',
            end: '00:30',
        };

        expect(payload(form, HOUSTON, false).ends_at).toBe(
            '2026-11-25T06:30:00.000Z',
        );
    });

    it("writes when in the reader's language", () => {
        expect(eventWhen('2026-11-24T20:00:00Z', false, 'en-US', HOUSTON)).toBe(
            'Tue, Nov 24, 2:00 PM',
        );
        expect(eventWhen('2026-11-27T00:00:00Z', true, 'en-US', HOUSTON)).toBe(
            'Fri, Nov 27',
        );
    });
});
