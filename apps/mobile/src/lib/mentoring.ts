/**
 * The mentor's workspace on the phone (A13.1b; mentor screens 22–24 and
 * 27): Today, mentees, mentee detail with private notes and goals, and the
 * session wrap-up.
 */
import type { paths } from '@reos/api-client';

import { api } from '@/lib/api';

type Got<P extends keyof paths> = paths[P] extends { get: { responses: { 200: { content: { 'application/json': infer B } } } } } ? B : never;

export type MentorToday = Got<'/mentoring/today'>['data'];
export type Mentee = Got<'/mentoring/mentees'>['data'][number];
export type MenteeDetail = Got<'/mentoring/mentees/{agent}'>['data'];
export type WrapUpDraft = Got<'/bookings/{booking}/wrap-up'>['data'];
export type MenteeFilter = 'all' | 'assigned' | 'booked';

const fail = (error: unknown): never => {
  const e = (error as { errors?: Record<string, string[]>; message?: string } | undefined) ?? {};
  throw new Error(Object.values(e.errors ?? {})[0]?.[0] ?? e.message ?? 'error');
};

export async function loadMentorToday(): Promise<MentorToday | null> {
  const { data } = await api().GET('/mentoring/today');

  return data ? data.data : null;
}

export async function loadMentees(filter: MenteeFilter): Promise<Mentee[]> {
  const { data } = await api().GET('/mentoring/mentees', { params: { query: { filter } } });

  return data ? data.data : [];
}

export async function loadMentee(agent: number): Promise<MenteeDetail | null> {
  const { data } = await api().GET('/mentoring/mentees/{agent}', { params: { path: { agent } } });

  return data ? data.data : null;
}

export async function addMenteeNote(agent: number, body: string): Promise<void> {
  const { error } = await api().POST('/mentoring/mentees/{agent}/notes', { params: { path: { agent } }, body: { body } });

  if (error) fail(error);
}

export async function removeMenteeNote(note: string, restore = false): Promise<void> {
  const { error } = restore
    ? await api().POST('/mentoring/notes/{note}/restore', { params: { path: { note } } })
    : await api().DELETE('/mentoring/notes/{note}', { params: { path: { note } } });

  if (error) fail(error);
}

export async function addMenteeGoal(agent: number, title: string, dueDate: string | null): Promise<void> {
  const { error } = await api().POST('/mentoring/mentees/{agent}/goals', { params: { path: { agent } }, body: { title, due_date: dueDate } });

  if (error) fail(error);
}

export async function loadWrapUp(uuid: string): Promise<WrapUpDraft | null> {
  const { data } = await api().GET('/bookings/{booking}/wrap-up', { params: { path: { booking: uuid } } });

  return data ? data.data : null;
}
