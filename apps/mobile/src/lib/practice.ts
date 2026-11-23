/**
 * Candidate practice and the study plan on the phone (A8.3, ADR 0032):
 * Today's plan, the practice hub, and timed tests saved after every tap.
 */
import type { paths, Schemas } from '@reos/api-client';
import { router, useFocusEffect } from 'expo-router';
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
export type ReadinessMap = Got<'/practice/readiness'>['data'];
export type ReadinessTopic = ReadinessMap['topics'][number];
export type Topic = 'agency' | 'contracts' | 'ownership' | 'finance' | 'math' | 'state_rules';

export type TestKind = 'diagnostic' | 'topic' | 'national' | 'state' | 'simulation' | 'drill' | 'retest' | 'challenge';
export type Coach = Got<'/practice/hub'>['data'];
export type TopicPlan = Got<'/practice/topic-plans/{plan}'>['data'];
export type PlanStep = TopicPlan['steps'][number];
export type PlanNext = Posted<'/practice/topic-plans/{plan}/continue'>['data'];
export type MathKind = Got<'/practice/math'>['data'][number];
export type MathCheck = Posted<'/practice/math/check'>['data'];
export type MathProblem = Posted<'/practice/math/problems'>['data'];
export type ExamKind = 'national' | 'state' | 'simulation';
export type WeeklyProgress = Got<'/practice/reports/weekly'>['data'];
export type ReportShares = Posted<'/practice/reports/weekly/share'>['data'];
export const isExam = (kind: string): kind is ExamKind => kind === 'national' || kind === 'state' || kind === 'simulation';

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

/** The practice hub's ranking and the smart drill (screen 38, A9.2b). */
export async function loadCoach(): Promise<Coach | null> {
  const { data } = await api().GET('/practice/hub');

  return data?.data ?? null;
}

/** Hint in a smart drill: the wrong option to strike out. */
export async function takeHint(attempt: number, question: number): Promise<number | null> {
  const { data } = await api().POST('/practice/attempts/{attempt}/questions/{question}/hint', { params: { path: { attempt, question } } });

  return data?.data.eliminated ?? null;
}

/** Fix a weak topic (screen 46): the plan under way for the topic, or a new one. */
export async function startPlan(topic: Topic): Promise<TopicPlan | null> {
  const { data } = await api().POST('/practice/topic-plans', { body: { topic } });

  return data?.data ?? null;
}

export async function loadPlan(plan: number): Promise<TopicPlan | null> {
  const { data } = await api().GET('/practice/topic-plans/{plan}', { params: { path: { plan } } });

  return data?.data ?? null;
}

export async function continuePlan(plan: number): Promise<PlanNext | null> {
  const { data } = await api().POST('/practice/topic-plans/{plan}/continue', { params: { path: { plan } } });

  return data?.data ?? null;
}

/** Where a fix plan's next step goes: the lesson at its second, the step's test, or the plan's misses. */
export function openStep(plan: number, next: PlanNext, topic: string | null = null) {
  if (next.kind === 'flashcards') {
    router.push({ pathname: '/practice/flashcards', params: topic ? { topic } : {} });
  } else if (next.lesson) {
    router.push({ pathname: '/learn/[id]', params: { id: String(next.lesson.id), ...(next.lesson.seconds !== null ? { t: String(next.lesson.seconds) } : {}) } });
  } else if (next.attempt_id !== null) {
    router.push({ pathname: '/practice/[id]', params: { id: String(next.attempt_id) } });
  } else {
    router.push({ pathname: '/practice/fix/[id]', params: { id: String(plan), misses: '1' } });
  }
}

/** Ask why (screen 45, A9.2e): the lesson that teaches a question, and the question to ask there; null if none. */
export async function askWhy(attempt: number, question: number): Promise<Got<'/practice/attempts/{attempt}/why/{question}'>['data']> {
  const { data } = await api().GET('/practice/attempts/{attempt}/why/{question}', { params: { path: { attempt, question } } });

  return data?.data ?? null;
}

/** Weekly progress (screen 55, A9.2e): the week holding `week` (this week by default), and how it's shared. */
export async function loadWeeklyProgress(week?: string): Promise<WeeklyProgress | null> {
  const { data } = await api().GET('/practice/reports/weekly', { params: { query: week ? { week } : {} } });

  return data?.data ?? null;
}

/** Share a week with the school, or as a first-name link for a study buddy (14 days). */
export async function shareWeek(withWho: 'school' | 'link', week: string): Promise<ReportShares | null> {
  const { data } = await api().POST('/practice/reports/weekly/share', { body: { with: withWho, week } });

  return data?.data ?? null;
}

/** Math lab (screen 48, A9.2c): the kinds with how many solved. */
export async function loadMathKinds(): Promise<MathKind[]> {
  const { data } = await api().GET('/practice/math');

  return data?.data ?? [];
}

export type MathLabKind = Schemas['MathCheckRequest']['kind'];
export const MATH_KINDS: MathLabKind[] = ['proration', 'commission', 'ltv', 'area', 'cap', 'net', 'points'];
export const asMathKind = (value: string | null | undefined): MathLabKind | null => ((MATH_KINDS as string[]).includes(value ?? '') ? (value as MathLabKind) : null);

/** A new problem of this kind; the answers stay on the server. */
export async function newMathProblem(kind: MathLabKind): Promise<MathProblem | null> {
  const { data } = await api().POST('/practice/math/problems', { body: { kind } });

  return data?.data ?? null;
}

/** "$1,250.50" as a number; null when it isn't one. */
export function mathValue(text: string): number | null {
  const value = Number(text.replace(/[$,%\s]/g, ''));

  return text.trim() === '' || Number.isNaN(value) ? null : value;
}

/** Check one step's value, or (`null`) show its answer. */
export async function checkMathStep(problem: MathProblem, step: number, value: number | null): Promise<MathCheck | null> {
  const kind = asMathKind(problem.kind);
  if (!kind) return null;
  const { data } = await api().POST('/practice/math/check', { body: { kind, seed: problem.seed, step, ...(value === null ? { reveal: true } : { value }) } });

  return data?.data ?? null;
}

/** The Readiness map and What you're good at (screens 54 and 44, A9.2). */
export async function loadReadiness(): Promise<ReadinessMap | null> {
  const { data } = await api().GET('/practice/readiness');

  return data?.data ?? null;
}

export async function loadHub(): Promise<HubTest[]> {
  const { data } = await api().GET('/practice/tests');

  return data?.data ?? [];
}

/** Start a test (or carry on with the same one still open). */
export async function startTest(kind: Exclude<TestKind, 'retest' | 'challenge'>, topic: Topic | null = null, mode: 'exam' | 'practice' | null = null): Promise<Attempt | null> {
  const { data } = await api().POST('/practice/tests', { body: { kind, topic, ...(mode === null ? {} : { mode }) } });

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

/** Practice mode only: stop or restart the clock. */
export async function pauseTest(attempt: number, paused: boolean): Promise<Attempt | null> {
  const { data } = await api().POST('/practice/attempts/{attempt}/pause', { params: { path: { attempt } }, body: { paused } });

  return data?.data ?? null;
}

/** Finish this portion: a simulation's next portion starts now; the last one submits. */
export async function finishPortion(attempt: number): Promise<Attempt | null> {
  const { data } = await api().POST('/practice/attempts/{attempt}/finish', { params: { path: { attempt } } });

  return data?.data ?? null;
}

export type Similar = NonNullable<Got<'/practice/attempts/{attempt}/similar/{question}'>['data']>;

export async function similarQuestion(attempt: number, question: number): Promise<Similar | null> {
  const { data } = await api().GET('/practice/attempts/{attempt}/similar/{question}', { params: { path: { attempt, question } } });

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

/** Challenges (screen 52, A9.3b): a challenge's board, first names only. */
export type ChallengeBoard = Got<'/practice/challenges/{challenge}'>['data'];

export async function loadChallenges(): Promise<ChallengeBoard[]> {
  const { data } = await api().GET('/practice/challenges');

  return data?.data ?? [];
}

export async function loadChallenge(uuid: string): Promise<ChallengeBoard | null> {
  const { data } = await api().GET('/practice/challenges/{challenge}', { params: { path: { challenge: uuid } } });

  return data?.data ?? null;
}

/** Challenge a friend: 10 questions, my own attempt started, and the link to share. */
export async function createChallenge(): Promise<ChallengeBoard | null> {
  const { data } = await api().POST('/practice/challenges');

  return data?.data ?? null;
}

export async function acceptChallenge(uuid: string): Promise<ChallengeBoard | null> {
  const { data } = await api().POST('/practice/challenges/{challenge}/accept', { params: { path: { challenge: uuid } } });

  return data?.data ?? null;
}

/** Study groups (screen 52, A9.3c): opt-in from Profile, a weekly leaderboard, first names only. */
export type StudyGroups = Got<'/practice/groups'>['data'];
export type StudyGroup = StudyGroups['groups'][number];

export async function loadGroups(): Promise<StudyGroups | null> {
  const { data } = await api().GET('/practice/groups');

  return data?.data ?? null;
}

export async function setGroupsOptIn(on: boolean): Promise<StudyGroups | null> {
  const { data } = await api().PUT('/practice/groups/opt-in', { body: { opt_in: on } });

  return data?.data ?? null;
}

export async function createGroup(name: string): Promise<StudyGroup | null> {
  const { data } = await api().POST('/practice/groups', { body: { name } });

  return data?.data ?? null;
}

export async function loadGroup(uuid: string): Promise<StudyGroup | null> {
  const { data } = await api().GET('/practice/groups/{group}', { params: { path: { group: uuid } } });

  return data?.data ?? null;
}

/** Join by invite link; the error's message when it can't (not opted in, or full). */
export async function joinGroup(uuid: string): Promise<{ group: StudyGroup | null; error: string | null }> {
  const { data, error } = await api().POST('/practice/groups/{group}/join', { params: { path: { group: uuid } } });

  return { group: data?.data ?? null, error: data ? null : ((error as { message?: string } | undefined)?.message ?? null) };
}

export async function leaveGroup(uuid: string): Promise<void> {
  await api().POST('/practice/groups/{group}/leave', { params: { path: { group: uuid } } });
}

export async function challengeGroup(uuid: string): Promise<ChallengeBoard | null> {
  const { data } = await api().POST('/practice/groups/{group}/challenge', { params: { path: { group: uuid } } });

  return data?.data ?? null;
}
