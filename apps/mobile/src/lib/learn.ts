/**
 * Learn on the phone (A5.3b; candidate screen 8): courses for my current
 * state, search inside every lesson, and a lesson opened at the heading,
 * page or second a result points to. Entitlements join in B5.1.
 */
import type { paths, Schemas } from '@reos/api-client';
import type { TFunction } from 'i18next';
import { useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';

import { api } from '@/lib/api';
import { useAuth } from '@/lib/auth';

type Json<P extends keyof paths> = paths[P] extends { get: { responses: { 200: { content: { 'application/json': infer B } } } } } ? B : never;

export type LearnCourse = Json<'/learn/courses'>['data'][number];
export type SearchHit = Schemas['KnowledgeHitResource'];
export type LearnLesson = Json<'/learn/lessons/{lesson}'>['data'];

export function useLearnCourses() {
  const { status } = useAuth();
  const [courses, setCourses] = useState<LearnCourse[] | null>(null);
  const [state, setState] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (status !== 'signedIn') return;

    const { data } = await api().GET('/learn/courses');
    setCourses(data?.data ?? []);
    setState(data?.state ?? null);
  }, [status]);

  useFocusEffect(
    useCallback(() => {
      void load();
    }, [load]),
  );

  return { courses, state };
}

export async function searchLessons(q: string): Promise<SearchHit[]> {
  const { data } = await api().GET('/learn/search', { params: { query: { q } } });

  return data?.data ?? [];
}

export async function loadLesson(id: number): Promise<LearnLesson | null> {
  const { data } = await api().GET('/learn/lessons/{lesson}', { params: { path: { lesson: id } } });

  return data?.data ?? null;
}

/** "4:05" or "1:02:09". */
export function clock(seconds: number): string {
  const h = Math.floor(seconds / 3600);
  const m = Math.floor((seconds % 3600) / 60);
  const s = String(seconds % 60).padStart(2, '0');

  return h > 0 ? `${h}:${String(m).padStart(2, '0')}:${s}` : `${m}:${s}`;
}

/** Where a result opens: "At 4:05", "Page 6" or its heading. */
export function where(t: TFunction, hit: { page: number | null; seconds: number | null; heading: string | null }): string {
  if (hit.seconds !== null) return t('content.where.at', { time: clock(hit.seconds) });
  if (hit.page !== null) return t('content.where.page', { page: hit.page });

  return hit.heading ?? t('content.where.text');
}

/** A snippet in plain and matching parts ([[match]]). */
export function marks(snippet: string): { text: string; match: boolean }[] {
  return snippet
    .split(/(\[\[.*?\]\])/)
    .filter((part) => part !== '')
    .map((part) => (part.startsWith('[[') && part.endsWith(']]') ? { text: part.slice(2, -2), match: true } : { text: part, match: false }));
}
