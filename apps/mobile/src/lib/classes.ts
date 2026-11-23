/**
 * Live classes on the phone (A10.2b; candidate L12, instructor i11 and
 * i12): classes for my courses (or the ones I teach), registering, and
 * the class tools in the room. The class's state is fetched again on
 * `class.changed` and every 15 seconds.
 */
import type { paths } from '@reos/api-client';
import { useCallback, useEffect, useState } from 'react';

import { api } from '@/lib/api';
import { listen } from '@/lib/realtime';

type Got<P extends keyof paths> = paths[P] extends { get: { responses: { 200: { content: { 'application/json': infer B } } } } } ? B : never;

export type ClassList = Got<'/classes'>;
export type LiveClassRow = ClassList['data'][number];
export type ClassLive = Got<'/classes/{session}/live'>['data'];
export type ClassReport = Got<'/classes/{session}/report'>['data'];

const fail = (error: unknown): never => {
  const e = (error as { errors?: Record<string, string[]>; message?: string } | undefined) ?? {};
  throw new Error(Object.values(e.errors ?? {})[0]?.[0] ?? e.message ?? 'error');
};

export async function loadClasses(course?: number): Promise<ClassList | null> {
  const { data } = await api().GET('/classes', { params: { query: course ? { course } : {} } });

  return data ?? null;
}

export async function registerForClass(id: string): Promise<LiveClassRow> {
  const { data, error } = await api().POST('/classes/{session}/registration', { params: { path: { session: id } } });

  return data ? data.data : fail(error);
}

export async function unregisterFromClass(id: string): Promise<LiveClassRow> {
  const { data, error } = await api().DELETE('/classes/{session}/registration', { params: { path: { session: id } } });

  return data ? data.data : fail(error);
}

/** The CE class report (A10.2c, i13). */
export async function loadReport(id: string): Promise<ClassReport | null> {
  const { data } = await api().GET('/classes/{session}/report', { params: { path: { session: id } } });

  return data?.data ?? null;
}

export async function decideCase(id: string, user: number, accept: boolean, reason: string): Promise<ClassReport> {
  const { data, error } = await api().POST('/classes/{session}/report/decisions', { params: { path: { session: id } }, body: { user, decision: accept ? 'accepted' : 'rejected', reason } });

  return data ? data.data : fail(error);
}

export async function sendReport(id: string): Promise<ClassReport> {
  const { data, error } = await api().POST('/classes/{session}/report/send', { params: { path: { session: id } } });

  return data ? data.data : fail(error);
}

type Group = 'thisWeek' | 'nextWeek' | 'later' | 'past';

/** Monday 00:00 of the week `weeks` from now, local time. */
function weekStart(weeks: number, now: number): number {
  const d = new Date(now);
  d.setHours(0, 0, 0, 0);
  d.setDate(d.getDate() - ((d.getDay() + 6) % 7) + weeks * 7);

  return d.getTime();
}

/** This week, next week, later and past (instructor i11). */
export function groupClasses(rows: LiveClassRow[], now = Date.now()): { group: Group; rows: LiveClassRow[] }[] {
  const groups: Record<Group, LiveClassRow[]> = { thisWeek: [], nextWeek: [], later: [], past: [] };

  for (const row of rows) {
    const starts = new Date(row.starts_at).getTime();

    if (row.status === 'ended' || row.status === 'cancelled' || new Date(row.ends_at).getTime() <= now) groups.past.push(row);
    else if (starts < weekStart(1, now)) groups.thisWeek.push(row);
    else if (starts < weekStart(2, now)) groups.nextWeek.push(row);
    else groups.later.push(row);
  }

  groups.past.reverse();

  return (['thisWeek', 'nextWeek', 'later', 'past'] as const).map((group) => ({ group, rows: groups[group] })).filter((g) => g.rows.length > 0);
}

/** "Tue, Nov 24 · 10:30 AM – 12:30 PM". */
export function classTime(row: { starts_at: string; ends_at: string }, language: string): string {
  const start = new Date(row.starts_at);
  const day = start.toLocaleDateString(language, { weekday: 'short', month: 'short', day: 'numeric' });
  const fmt = (d: Date) => d.toLocaleTimeString(language, { hour: 'numeric', minute: '2-digit' });

  return `${day} · ${fmt(start)} – ${fmt(new Date(row.ends_at))}`;
}

/** The class's state and its actions, for the room. */
export function useClassLive(id: string, userId: number | null) {
  const [live, setLive] = useState<ClassLive | null>(null);
  const [problem, setProblem] = useState<string | null>(null);

  const load = useCallback(() => {
    void api()
      .GET('/classes/{session}/live', { params: { path: { session: id } } })
      .then(({ data }) => {
        if (data) setLive(data.data);
      })
      .catch(() => undefined);
  }, [id]);

  useEffect(() => {
    load();
    const timer = setInterval(load, 15_000);

    return () => clearInterval(timer);
  }, [load]);

  useEffect(
    () =>
      userId === null
        ? undefined
        : listen(`users.${userId}`, ['class.changed'], (event) => {
            if ((event as { session?: string } | undefined)?.session === id) load();
          }),
    [id, userId, load],
  );

  const run = useCallback(async (work: () => Promise<{ data?: { data: ClassLive }; error?: unknown }>) => {
    try {
      const { data, error } = await work();
      if (data) {
        setLive(data.data);
        setProblem(null);
      } else fail(error);
    } catch (e) {
      setProblem(e instanceof Error ? e.message : String(e));
    }
  }, []);

  const path = { session: id };
  const actions = {
    hand: (raised: boolean) => run(() => api().POST('/classes/{session}/hand', { params: { path }, body: { raised } })),
    vote: (poll: number, option: number) => run(() => api().POST('/classes/{session}/polls/{poll}/votes', { params: { path: { ...path, poll } }, body: { option } })),
    closePoll: (poll: number) => run(() => api().POST('/classes/{session}/polls/{poll}/close', { params: { path: { ...path, poll } } })),
    poll: (question: string, options: string[]) => run(() => api().POST('/classes/{session}/polls', { params: { path }, body: { question, options } })),
    ask: (body: string) => run(() => api().POST('/classes/{session}/questions', { params: { path }, body: { body } })),
    like: (question: number, like: boolean) => run(() => api().POST('/classes/{session}/questions/{question}/like', { params: { path: { ...path, question } }, body: { like } })),
    answered: (question: number, answered: boolean) =>
      run(() => api().POST('/classes/{session}/questions/{question}/answered', { params: { path: { ...path, question } }, body: { answered } })),
    chat: (body: string) => run(() => api().POST('/classes/{session}/messages', { params: { path }, body: { body } })),
    stage: (user: number, on: boolean) => run(() => api().POST('/classes/{session}/stage', { params: { path }, body: { user, on } })),
    sendCheck: () => run(() => api().POST('/classes/{session}/checks', { params: { path } })),
    answerCheck: (check: number) => run(() => api().POST('/classes/{session}/checks/{check}/answer', { params: { path: { ...path, check } } })),
  };

  return { live, problem, actions, reload: load };
}

export type ClassActions = ReturnType<typeof useClassLive>['actions'];
