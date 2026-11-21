import type { TFunction } from 'i18next';
import type { CalendarEvent, CalendarKind } from '@/types/calendar';

/** Kinds people choose when making an appointment (`event` is a tool's own). */
export const KINDS: Exclude<CalendarKind, 'event' | 'todo'>[] = [
    'appointment',
    'showing',
    'deadline',
    'session',
    'ce',
];

/** What an appointment form holds, as the person sees it (wall time in their zone). */
export type AppointmentForm = {
    title: string;
    kind: Exclude<CalendarKind, 'event' | 'todo'>;
    allDay: boolean;
    /** `YYYY-MM-DD` */
    date: string;
    /** `HH:MM` */
    start: string;
    end: string;
    /** All-day: last day, inclusive. */
    endDate: string;
    location: string;
    /** Comma-separated emails. */
    guests: string;
    notes: string;
    notify: boolean;
};

/** Minutes a zone is ahead of UTC at a moment. */
function offsetMinutes(at: Date, timeZone: string): number {
    const parts = Object.fromEntries(
        new Intl.DateTimeFormat('en-US', {
            timeZone,
            hourCycle: 'h23',
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        })
            .formatToParts(at)
            .map((part) => [part.type, part.value]),
    );
    const asUtc = Date.UTC(
        Number(parts.year),
        Number(parts.month) - 1,
        Number(parts.day),
        Number(parts.hour),
        Number(parts.minute),
        Number(parts.second),
    );

    return Math.round((asUtc - at.getTime()) / 60_000);
}

/** A wall time in a zone ("2026-11-24", "14:30", "America/Chicago") as an ISO instant. */
export function zonedToIso(
    date: string,
    time: string,
    timeZone: string,
): string {
    const [y, m, d] = date.split('-').map(Number);
    const [h, min] = time.split(':').map(Number);
    const wall = Date.UTC(y ?? 1970, (m ?? 1) - 1, d ?? 1, h ?? 0, min ?? 0);
    // Twice, so a guess on the other side of a daylight-saving change settles.
    let instant = wall - offsetMinutes(new Date(wall), timeZone) * 60_000;
    instant = wall - offsetMinutes(new Date(instant), timeZone) * 60_000;

    return new Date(instant).toISOString();
}

/** An instant as wall date and time in a zone. */
export function isoToZoned(
    iso: string,
    timeZone: string,
): { date: string; time: string } {
    const parts = Object.fromEntries(
        new Intl.DateTimeFormat('en-CA', {
            timeZone,
            hourCycle: 'h23',
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
        })
            .formatToParts(new Date(iso))
            .map((part) => [part.type, part.value]),
    );

    return {
        date: `${parts.year}-${parts.month}-${parts.day}`,
        time: `${parts.hour}:${parts.minute}`,
    };
}

/** "10:00 AM" / "10:00 a. m.", or "All day". */
export function eventTime(
    event: Pick<CalendarEvent, 'all_day' | 'starts_at'>,
    t: TFunction,
    language: string,
    timeZone: string,
): string {
    if (event.all_day) {
        return t('calendar.allDay');
    }

    return new Intl.DateTimeFormat(language, {
        timeZone,
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(event.starts_at));
}

/** "Tue, Nov 24, 3:00 PM" (or the date alone for all-day). */
export function eventWhen(
    startsAt: string | null,
    allDay: boolean,
    language: string,
    timeZone: string,
): string {
    if (startsAt === null) {
        return '';
    }

    return new Intl.DateTimeFormat(language, {
        timeZone: allDay ? 'UTC' : timeZone,
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        ...(allDay ? {} : { hour: 'numeric', minute: '2-digit' }),
    }).format(new Date(startsAt));
}

/** "Nov 24" / "24 nov". */
export function shortDate(iso: string, language: string): string {
    return new Intl.DateTimeFormat(language, {
        month: 'short',
        day: 'numeric',
    }).format(new Date(iso));
}

/** Where an event comes from: the tool's name, or REOS. */
export function sourceName(event: CalendarEvent, t: TFunction): string {
    return event.source === 'reos'
        ? (event.tool ?? t('calendar.reos'))
        : (event.tool ?? event.source);
}

/** The title to show; events shared as busy only have none. */
export function eventTitle(event: CalendarEvent, t: TFunction): string {
    if (event.busy) {
        return t('calendar.busy');
    }

    return event.title ?? t('calendar.untitled');
}

/** A new appointment on a day: the next whole hour today, 10 AM on other days, one hour long. */
export function blankForm(
    date: string,
    timeZone: string,
    now: Date = new Date(),
): AppointmentForm {
    const here = isoToZoned(now.toISOString(), timeZone);
    const nextHour = Math.min(Number(here.time.slice(0, 2)) + 1, 22);
    const start = here.date === date ? nextHour : 10;
    const pad = (n: number) => String(n).padStart(2, '0');

    return {
        title: '',
        kind: 'appointment',
        allDay: false,
        date,
        start: `${pad(start)}:00`,
        end: `${pad(start + 1)}:00`,
        endDate: date,
        location: '',
        guests: '',
        notes: '',
        notify: false,
    };
}

export function formFromEvent(
    event: CalendarEvent,
    timeZone: string,
): AppointmentForm {
    const start = isoToZoned(event.starts_at, timeZone);
    const end = isoToZoned(event.ends_at, timeZone);

    return {
        title: event.title ?? '',
        kind:
            event.kind === 'event' || event.kind === 'todo'
                ? 'appointment'
                : event.kind,
        allDay: event.all_day,
        date: event.all_day ? (event.start_date ?? start.date) : start.date,
        start: start.time,
        end: end.time,
        endDate: event.all_day ? (event.end_date ?? start.date) : end.date,
        location: event.location ?? '',
        guests: event.attendees.join(', '),
        notes: event.description ?? '',
        notify: event.notify_guests,
    };
}

/** What the server takes (`SaveCalendarEventRequest`). */
export type AppointmentPayload = {
    title: string;
    kind?: AppointmentForm['kind'];
    all_day: boolean;
    starts_at?: string;
    ends_at?: string;
    start_date?: string;
    end_date?: string;
    location: string | null;
    description: string | null;
    attendees: string[];
    notify_guests: boolean;
};

export function payload(
    form: AppointmentForm,
    timeZone: string,
    includeKind: boolean,
): AppointmentPayload {
    const guests = form.guests
        .split(/[,;\s]+/)
        .map((email) => email.trim())
        .filter(Boolean);
    const when = form.allDay
        ? { all_day: true, start_date: form.date, end_date: form.endDate }
        : {
              all_day: false,
              starts_at: zonedToIso(form.date, form.start, timeZone),
              // An end before the start means "after midnight".
              ends_at: zonedToIso(
                  form.end <= form.start ? nextDay(form.date) : form.date,
                  form.end,
                  timeZone,
              ),
          };

    return {
        title: form.title.trim(),
        ...(includeKind ? { kind: form.kind } : {}),
        ...when,
        location: form.location.trim() || null,
        description: form.notes.trim() || null,
        attendees: guests,
        notify_guests: form.notify,
    };
}

export function nextDay(date: string): string {
    const day = new Date(`${date}T00:00:00Z`);
    day.setUTCDate(day.getUTCDate() + 1);

    return day.toISOString().slice(0, 10);
}

/**
 * Tell the server which time zone this browser is in (`reos_tz`), so "today"
 * on the dashboard is the person's today. Texas time until it knows.
 */
export function rememberTimezone(): void {
    const zone = Intl.DateTimeFormat().resolvedOptions().timeZone;

    if (
        zone &&
        !document.cookie.includes(`reos_tz=${encodeURIComponent(zone)}`)
    ) {
        document.cookie = `reos_tz=${encodeURIComponent(zone)};path=/;max-age=${365 * 86400};SameSite=Lax`;
    }
}
