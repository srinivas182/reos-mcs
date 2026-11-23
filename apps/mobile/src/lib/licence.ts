/**
 * The licence journey on the phone (A10.3a; candidate screens 16, 15, 14
 * and 19): the state's steps, the application window, the exam date, the
 * exam-day kit and the result with its retake plan.
 */
import type { paths } from '@reos/api-client';
import { useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';

import { api } from '@/lib/api';

type Got<P extends keyof paths> = paths[P] extends { get: { responses: { 200: { content: { 'application/json': infer B } } } } } ? B : never;

export type Journey = Got<'/licence-journey'>['data'];
export type Portion = 'national' | 'state';
export const TOPICS = ['agency', 'contracts', 'ownership', 'finance', 'math', 'state_rules'] as const;
export type Topic = (typeof TOPICS)[number];

const fail = (error: unknown): never => {
  const e = (error as { errors?: Record<string, string[]>; message?: string } | undefined) ?? {};
  throw new Error(Object.values(e.errors ?? {})[0]?.[0] ?? e.message ?? 'error');
};

const take = ({ data, error }: { data?: { data: Journey }; error?: unknown }): Journey => (data ? data.data : fail(error));

/** The journey, loaded each time the screen comes into view. */
export function useJourney() {
  const [journey, setJourney] = useState<Journey | null>(null);
  const [problem, setProblem] = useState<string | null>(null);

  useFocusEffect(
    useCallback(() => {
      void api()
        .GET('/licence-journey')
        .then(({ data }) => setJourney(data?.data ?? null))
        .catch(() => undefined);
    }, []),
  );

  const run = useCallback(async (work: () => Promise<Journey>) => {
    try {
      setJourney(await work());
      setProblem(null);

      return true;
    } catch (e) {
      setProblem(e instanceof Error ? e.message : String(e));

      return false;
    }
  }, []);

  return { journey, problem, run };
}

export const checkStep = async (code: string, done: boolean, on?: string) =>
  take(await api().PUT('/licence-journey/steps/{code}', { params: { path: { code } }, body: { done, on: on ?? null } }));

export const setExam = async (examOn: string | null, time: string | null, centre: string | null) =>
  take(await api().PUT('/licence-journey/exam', { body: { exam_on: examOn, time, centre } }));

export const tickKit = async (item: string, done: boolean) => take(await api().PUT('/licence-journey/kit/{item}', { params: { path: { item } }, body: { done } }));

export const enterResult = async (national: boolean | null, state: boolean | null, weakTopics: Topic[]) =>
  take(await api().POST('/licence-journey/results', { body: { national, state, weak_topics: weakTopics } }));

/** "Mon, Dec 14" from "2026-12-14", whatever the phone's zone. */
export const dayText = (day: string, language: string): string =>
  new Intl.DateTimeFormat(language, { weekday: 'short', month: 'short', day: 'numeric', timeZone: 'UTC' }).format(new Date(`${day}T00:00:00Z`));

/** Today as "2026-11-23" on this phone. */
export const todayIso = (): string => {
  const d = new Date();
  const pad = (n: number) => String(n).padStart(2, '0');

  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
};
