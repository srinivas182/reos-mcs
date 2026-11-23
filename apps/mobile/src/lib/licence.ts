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
export type PassFeed = Got<'/licence-journey/passes'>['data'];
export type CandidateOffers = Got<'/licence-journey/offers'>['data'];
export type Offer = CandidateOffers['offers'][number];
export type Portion = 'national' | 'state';
export const TOPICS = ['agency', 'contracts', 'ownership', 'finance', 'math', 'state_rules'] as const;
export type Topic = (typeof TOPICS)[number];

const fail = (error: unknown): never => {
  const e = (error as { errors?: Record<string, string[]>; message?: string } | undefined) ?? {};
  throw new Error(Object.values(e.errors ?? {})[0]?.[0] ?? e.message ?? 'error');
};

/** The journey again, after a change that returns something else. */
export async function reloadJourney(): Promise<Journey> {
  const { data, error } = await api().GET('/licence-journey');

  return data ? data.data : fail(error);
}

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

/** Share the news after passing (f05, A10.3b): who hears; first name and city only. */
export async function shareNews(choice: { to_group: boolean; to_school: boolean; to_state: boolean; city: string | null; tip: string | null }): Promise<void> {
  const { error, response } = await api().POST('/licence-journey/news', { body: choice });
  if (!response.ok) fail(error);
}

export async function skipNews(): Promise<void> {
  const { error, response } = await api().POST('/licence-journey/news/skip');
  if (!response.ok) fail(error);
}

export async function loadPasses(): Promise<PassFeed | null> {
  const { data } = await api().GET('/licence-journey/passes');

  return data?.data ?? null;
}

export async function sendCongrats(pass: number, message: string): Promise<PassFeed> {
  const { data, error } = await api().POST('/licence-journey/passes/{pass}/congrats', { params: { path: { pass } }, body: { message } });

  return data ? data.data : fail(error);
}

export async function loadOffers(): Promise<CandidateOffers | null> {
  const { data } = await api().GET('/licence-journey/offers');

  return data?.data ?? null;
}

export async function optInOffers(on: boolean, city: string | null): Promise<CandidateOffers> {
  const { data, error } = await api().PUT('/licence-journey/offers/opt-in', { body: { on, city } });

  return data ? data.data : fail(error);
}

export async function answerOffer(offer: string, accept: boolean): Promise<CandidateOffers> {
  const { data, error } = accept
    ? await api().POST('/licence-journey/offers/{offer}/accept', { params: { path: { offer } } })
    : await api().POST('/licence-journey/offers/{offer}/decline', { params: { path: { offer } } });

  return data ? data.data : fail(error);
}
