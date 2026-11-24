/**
 * The content calendar, celebrations, and the profile photo and brand kit
 * on the phone (B12.3; My posts, screens 162 and 163).
 */
import type { paths } from '@reos/api-client';

import { api } from '@/lib/api';

type Got<P extends keyof paths> = paths[P] extends { get: { responses: { 200: { content: { 'application/json': infer B } } } } } ? B : never;

export type Calendar = Got<'/social/calendar'>['data'];
export type CalendarPost = Calendar['days'][number]['posts'][number];
export type CelebrationList = Got<'/studio/celebrations'>['data'];
export type Celebration = Got<'/studio/celebrations/{celebration}'>['data'];
export type BrandKit = Got<'/me/brand'>['data'];

const fail = (error: unknown): never => {
  const e = (error as { errors?: Record<string, string[]>; message?: string } | undefined) ?? {};
  throw new Error(Object.values(e.errors ?? {})[0]?.[0] ?? e.message ?? 'error');
};

export async function loadCalendar(view: 'week' | 'month', date?: string): Promise<Calendar | null> {
  const { data } = await api().GET('/social/calendar', { params: { query: { view, ...(date ? { date } : {}) } } });

  return data ? data.data : null;
}

export async function movePost(id: string, date: string): Promise<CalendarPost> {
  const { data, error } = await api().POST('/social/posts/{post}/move', { params: { path: { post: id } }, body: { date } });

  return data ? data.data : fail(error);
}

export async function reusePost(id: string, date: string): Promise<CalendarPost> {
  const { data, error } = await api().POST('/social/posts/{post}/reuse', { params: { path: { post: id } }, body: { date } });

  return data ? data.data : fail(error);
}

export async function loadCelebrations(): Promise<CelebrationList | null> {
  const { data } = await api().GET('/studio/celebrations');

  return data ? data.data : null;
}

export async function loadCelebration(id: string): Promise<Celebration | null> {
  const { data } = await api().GET('/studio/celebrations/{celebration}', { params: { path: { celebration: id } } });

  return data ? data.data : null;
}

export async function loadBrandKit(): Promise<BrandKit | null> {
  const { data } = await api().GET('/me/brand');

  return data ? data.data : null;
}

export async function uploadProfilePhoto(file: { uri: string; name: string; type: string }): Promise<BrandKit> {
  const form = new FormData();
  form.append('photo', file as unknown as Blob);
  const { data, error } = await api().POST('/me/brand/photo', { body: { photo: '' }, bodySerializer: () => form });

  return data ? data.data : fail(error);
}

export async function removeProfilePhoto(): Promise<BrandKit> {
  const { data, error } = await api().DELETE('/me/brand/photo');

  return data ? data.data : fail(error);
}

/** A date (YYYY-MM-DD) moved by whole days, without time zones getting in the way. */
export const addDays = (date: string, days: number): string => {
  const d = new Date(`${date}T12:00:00Z`);
  d.setUTCDate(d.getUTCDate() + days);
  return d.toISOString().slice(0, 10);
};
