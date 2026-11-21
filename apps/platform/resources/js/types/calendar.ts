import type { Schemas } from '@reos/api-client';

/** One event on the person's REOS calendar (`CalendarEventResource`). */
export type CalendarEvent = Schemas['CalendarEventResource'];

export type CalendarKind = CalendarEvent['kind'];

/** The dashboard's "Today": the day's events, where new ones go, and deletions that can be undone. */
export type CalendarDay = {
    data: CalendarEvent[];
    write_to: {
        connection: number;
        tool: string;
        account: string | null;
        calendar: string;
    } | null;
    removed: {
        conflict: number;
        title: string | null;
        starts_at: string | null;
        at: string | null;
    }[];
    /** The day shown, `YYYY-MM-DD`, in `timezone`. */
    date: string;
    timezone: string;
};
