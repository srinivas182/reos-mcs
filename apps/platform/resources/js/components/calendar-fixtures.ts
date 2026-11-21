import type { CalendarDay, CalendarEvent } from '@/types/calendar';

/** Linda's showing, made in REOS and in her Google Calendar. */
export const calendarEvent = (
    overrides: Partial<CalendarEvent> = {},
): CalendarEvent => ({
    id: 41,
    title: 'Showing — 1234 Oak Ridge Dr',
    kind: 'showing',
    starts_at: '2026-11-21T20:00:00+00:00',
    ends_at: '2026-11-21T20:45:00+00:00',
    all_day: false,
    start_date: null,
    end_date: null,
    location: '1234 Oak Ridge Dr, Cypress, TX',
    description: null,
    attendees: [],
    notify_guests: false,
    busy: false,
    source: 'reos',
    tool: 'Google Calendar',
    calendar: 'linda.park@lonestarrealty.example',
    link: 'https://calendar.google.com/calendar/event?eid=demo',
    editable: true,
    deletable: true,
    sync: 'synced',
    sync_error: null,
    conflict: null,
    ...overrides,
});

/** A Saturday in Houston: an option-period deadline, a showing, a call from Google and a busy slot. */
export const calendarDay = (
    overrides: Partial<CalendarDay> = {},
): CalendarDay => ({
    date: '2026-11-21',
    timezone: 'America/Chicago',
    write_to: {
        connection: 7,
        tool: 'Google Calendar',
        account: 'linda.park@lonestarrealty.example',
        calendar: 'linda.park@lonestarrealty.example',
    },
    removed: [],
    data: [
        calendarEvent({
            id: 40,
            title: 'Option period ends — 1234 Oak Ridge Dr',
            kind: 'deadline',
            all_day: true,
            start_date: '2026-11-21',
            end_date: '2026-11-21',
            starts_at: '2026-11-21T00:00:00+00:00',
            ends_at: '2026-11-22T00:00:00+00:00',
            location: null,
        }),
        calendarEvent({
            id: 42,
            title: 'Call back Maria Lopez — pre-approval',
            kind: 'event',
            source: 'google-calendar',
            starts_at: '2026-11-21T16:00:00+00:00',
            ends_at: '2026-11-21T16:30:00+00:00',
            location: null,
            deletable: false,
        }),
        calendarEvent(),
        calendarEvent({
            id: 43,
            title: null,
            kind: 'event',
            source: 'google-calendar',
            busy: true,
            calendar: 'Personal',
            starts_at: '2026-11-22T00:00:00+00:00',
            ends_at: '2026-11-22T01:00:00+00:00',
            location: null,
            editable: false,
            deletable: false,
            link: null,
        }),
    ],
    ...overrides,
});
