/**
 * Candidate practice and the study plan on the phone (A8.3, ADR 0032):
 * Today's plan, the practice hub, and timed tests saved after every tap.
 */
import type { paths } from '@reos/api-client';
import { useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';

import { api } from '@/lib/api';

type Got<P extends keyof paths> = paths[P] extends { get: { responses: { 200: { content: { 'application/json': infer B } } } } } ? B : never;
type Posted<P extends keyof paths> = paths[P] extends { post: { responses: { 200: { content: { 'application/json': infer B } } } } } ? B : never;

export type CandidateToday = Got<'/me/study'>['data'];
export type StudyTask = CandidateToday['tasks'][number];
export type HubTest = Got<'/practice/tests'>['data'][number];
export type Attempt = Got<'/practice/attempts/{attempt}'>['data'];
export type TestQuestion = Attempt['questions'][number];
export type Results = Posted<'/practice/attempts/{attempt}/submit'>['data'];
export type Topic = 'agency' | 'contracts' | 'ownership' | 'finance' | 'math' | 'state_rules';

export const TOPICS: Topic[] = ['agency', 'contracts', 'ownership', 'finance', 'math', 'state_rules'];

export const asTopic = (value: string | null | undefined): Topic | null => (TOPICS as string[]).includes(value ?? '') ? (value as Topic) : null;

/** Today for a candidate, loaded whenever the screen comes into view; null for anyone else. */
export function useCandidateToday(enabled: boolean) {
  const [today, setToday] = useState<CandidateToday | null>(null);

  const load = useCallback(async () => {
    if (!enabled) return;
    const { data } = await api()
      .GET('/me/study')
      .catch(() => ({ data: undefined }));
    setToday(data?.data ?? null);
  }, [enabled]);

  useFocusEffect(
    useCallback(() => {
      void load();
    }, [load]),
  );

  return { today, reload: load };
}

export async function loadHub(): Promise<HubTest[]> {
  const { data } = await api().GET('/practice/tests');

  return data?.data ?? [];
}

/** Start a test (or carry on with the same one still open). */
export async function startTest(kind: 'diagnostic' | 'topic', topic: Topic | null = null): Promise<Attempt | null> {
  const { data } = await api().POST('/practice/tests', { body: { kind, topic } });

  return data?.data ?? null;
}

export async function loadAttempt(id: number): Promise<Attempt | null> {
  const { data } = await api().GET('/practice/attempts/{attempt}', { params: { path: { attempt: id } } });

  return data?.data ?? null;
}

/** Saved the moment it's tapped. */
export async function saveAnswer(attempt: number, question: number, choice: number | null, flagged: boolean): Promise<TestQuestion | null> {
  const { data } = await api().PUT('/practice/attempts/{attempt}/answers/{question}', { params: { path: { attempt, question } }, body: { choice, flagged } });

  return data?.data ?? null;
}

export async function submitTest(attempt: number): Promise<Results | null> {
  const { data } = await api().POST('/practice/attempts/{attempt}/submit', { params: { path: { attempt } } });

  return data?.data ?? null;
}

export async function loadResults(attempt: number): Promise<Results | null> {
  const { data } = await api().GET('/practice/attempts/{attempt}/results', { params: { path: { attempt } } });

  return data?.data ?? null;
}

/** "12:05" or "1:02:09". */
export function countdown(seconds: number): string {
  const s = Math.max(0, Math.floor(seconds));
  const h = Math.floor(s / 3600);
  const m = Math.floor((s % 3600) / 60);
  const r = String(s % 60).padStart(2, '0');

  return h > 0 ? `${h}:${String(m).padStart(2, '0')}:${r}` : `${m}:${r}`;
}
