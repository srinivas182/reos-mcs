import type { Schemas } from '@reos/api-client';
import type { TFunction } from 'i18next';
import { useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';

import { api } from '@/lib/api';
import { useAuth } from '@/lib/auth';

/** One event on the person's REOS calendar (`CalendarEventResource`). */
export type CalendarEvent = Schemas['CalendarEventResource'];
export type CalendarKind = CalendarEvent['kind'];
export type AppointmentKind = Exclude<CalendarKind, 'event'>;

export type CalendarDay = {
  data: CalendarEvent[];
  writeTo: { tool: string; calendar: string } | null;
  removed: { conflict: number; title: string | null; at: string | null }[];
};

export const KINDS: AppointmentKind[] = ['appointment', 'showing', 'deadline', 'session', 'ce'];

/** Minutes, for the "How long" choices. */
export const DURATIONS = [30, 45, 60, 90, 120] as const;

const pad = (n: number) => String(n).padStart(2, '0');

/** A local moment as ISO with this phone's offset ("2026-11-21T00:00:00-06:00"), so the server knows which date is today. */
export function localIso(at: Date): string {
  const offset = -at.getTimezoneOffset();
  const sign = offset >= 0 ? '+' : '-';
  const abs = Math.abs(offset);

  return `${at.getFullYear()}-${pad(at.getMonth() + 1)}-${pad(at.getDate())}T${pad(at.getHours())}:${pad(at.getMinutes())}:00${sign}${pad(Math.floor(abs / 60))}:${pad(abs % 60)}`;
}

/** `YYYY-MM-DD` of a local day. */
export function localDate(at: Date): string {
  return `${at.getFullYear()}-${pad(at.getMonth() + 1)}-${pad(at.getDate())}`;
}

export function startOfDay(at: Date): Date {
  return new Date(at.getFullYear(), at.getMonth(), at.getDate());
}

export function addDays(at: Date, days: number): Date {
  return new Date(at.getFullYear(), at.getMonth(), at.getDate() + days, at.getHours(), at.getMinutes());
}

/** "10:00 AM", or "All day". */
export function eventTime(event: Pick<CalendarEvent, 'all_day' | 'starts_at'>, t: TFunction, language: string): string {
  return event.all_day ? t('calendar.allDay') : new Intl.DateTimeFormat(language, { hour: 'numeric', minute: '2-digit' }).format(new Date(event.starts_at));
}

/** "Tue, Nov 24, 3:00 PM" (the date alone for all-day). */
export function eventWhen(startsAt: string | null, allDay: boolean, language: string): string {
  if (startsAt === null) return '';

  return new Intl.DateTimeFormat(language, {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    ...(allDay ? { timeZone: 'UTC' } : { hour: 'numeric', minute: '2-digit' }),
  }).format(new Date(startsAt));
}

export function dayLabel(at: Date, language: string): string {
  return new Intl.DateTimeFormat(language, { weekday: 'long', month: 'long', day: 'numeric' }).format(at);
}

export function eventTitle(event: CalendarEvent, t: TFunction): string {
  return event.busy ? t('calendar.busy') : (event.title ?? t('calendar.untitled'));
}

export function sourceName(event: CalendarEvent, t: TFunction): string {
  return event.tool ?? (event.source === 'reos' ? t('calendar.reos') : event.source);
}

/** Today's events, reloaded on demand (after a change). */
export function useCalendarDay(day: Date = new Date()) {
  const { status } = useAuth();
  const [today, setToday] = useState<CalendarDay | null>(null);
  const from = startOfDay(day);
  const key = localIso(from);

  const load = useCallback(async () => {
    if (status !== 'signedIn') return;

    const start = new Date(key.slice(0, 19));
    const { data } = await api().GET('/calendar/events', {
      params: { query: { from: key, to: localIso(addDays(start, 1)) } },
    });

    if (data) {
      setToday({
        data: data.data,
        writeTo: data.write_to ? { tool: data.write_to.tool, calendar: data.write_to.calendar } : null,
        removed: data.removed.map((r) => ({ conflict: r.conflict, title: r.title, at: r.at })),
      });
    }
  }, [status, key]);

  useFocusEffect(
    useCallback(() => {
      void load();
    }, [load]),
  );

  return { today, reload: load };
}

/** What the appointment sheet holds. */
export type AppointmentForm = {
  title: string;
  kind: AppointmentKind;
  allDay: boolean;
  /** Local start (date and time). */
  start: Date;
  minutes: number;
  /** All-day: number of days. */
  days: number;
  location: string;
  guests: string;
  notes: string;
  notify: boolean;
};

/** The next whole hour today, 10 AM on other days; one hour. */
export function blankForm(day: Date, now: Date = new Date()): AppointmentForm {
  const today = localDate(day) === localDate(now);
  const hour = today ? Math.min(now.getHours() + 1, 22) : 10;

  return {
    title: '',
    kind: 'appointment',
    allDay: false,
    start: new Date(day.getFullYear(), day.getMonth(), day.getDate(), hour, 0),
    minutes: 60,
    days: 1,
    location: '',
    guests: '',
    notes: '',
    notify: false,
  };
}

export function formFromEvent(event: CalendarEvent): AppointmentForm {
  const start = event.all_day && event.start_date ? new Date(`${event.start_date}T00:00:00`) : new Date(event.starts_at);
  const span = (new Date(event.ends_at).getTime() - new Date(event.starts_at).getTime()) / 60_000;

  return {
    title: event.title ?? '',
    kind: event.kind === 'event' ? 'appointment' : event.kind,
    allDay: event.all_day,
    start,
    minutes: event.all_day ? 60 : Math.max(15, Math.round(span)),
    days: event.all_day ? Math.max(1, Math.round(span / 1440)) : 1,
    location: event.location ?? '',
    guests: event.attendees.join(', '),
    notes: event.description ?? '',
    notify: event.notify_guests,
  };
}

export type AppointmentPayload = Schemas['SaveCalendarEventRequest'];

export function payload(form: AppointmentForm, includeKind: boolean): AppointmentPayload {
  const attendees = form.guests
    .split(/[,;\s]+/)
    .map((email) => email.trim())
    .filter(Boolean);

  return {
    title: form.title.trim(),
    ...(includeKind ? { kind: form.kind } : {}),
    ...(form.allDay
      ? { all_day: true, start_date: localDate(form.start), end_date: localDate(addDays(form.start, form.days - 1)) }
      : { all_day: false, starts_at: form.start.toISOString(), ends_at: new Date(form.start.getTime() + form.minutes * 60_000).toISOString() }),
    location: form.location.trim() || null,
    description: form.notes.trim() || null,
    attendees,
    notify_guests: form.notify,
  };
}

/** The first error message the server sent back, if any. */
export function firstError(error: unknown): string | null {
  const errors = (error as { errors?: Record<string, string[]> } | undefined)?.errors;

  return Object.values(errors ?? {})[0]?.[0] ?? null;
}
