/**
 * Getting started before an account exists (mobile journey screens 1–7):
 * the path, state and free-quiz answers are kept on the phone, then saved
 * to the account straight after sign-up.
 */
import type { ApiClient, Schemas } from '@reos/api-client';
import { createMMKV } from 'react-native-mmkv';
import { useSyncExternalStore } from 'react';

export type OnboardingPath = Schemas['OnboardingPath'];
export type QuizAnswer = { question_id: number; choice: number };
export type QuizTopic = Schemas['QuizTopic'];
export type TopicLevel = 'strong' | 'learning' | 'start_here';

export type Draft = {
  path?: OnboardingPath;
  state?: string;
  answers?: QuizAnswer[];
  /** Each answer's topic and whether it was right, for the score screen. */
  results?: { topic: QuizTopic; correct: boolean }[];
};

const storage = createMMKV({ id: 'reos.onboarding' });
const DRAFT = 'draft';
const SIGNED_IN_BEFORE = 'signedInBefore';
const listeners = new Set<() => void>();

function read(): Draft {
  try {
    const value = JSON.parse(storage.getString(DRAFT) ?? '{}') as unknown;

    return typeof value === 'object' && value !== null ? (value as Draft) : {};
  } catch {
    return {};
  }
}

let current: Draft = read();

export const getDraft = (): Draft => current;

export function updateDraft(change: Partial<Draft>): void {
  current = { ...current, ...change };
  storage.set(DRAFT, JSON.stringify(current));
  listeners.forEach((listener) => listener());
}

export function clearDraft(): void {
  current = {};
  storage.remove(DRAFT);
  listeners.forEach((listener) => listener());
}

/** Draft choices, re-rendering when they change. */
export function useDraft(): Draft {
  return useSyncExternalStore(
    (listener) => {
      listeners.add(listener);

      return () => listeners.delete(listener);
    },
    () => current,
  );
}

/** Once someone has signed in on this phone, signing out shows Sign in, not the path chooser. */
export const hasSignedInBefore = (): boolean => storage.getBoolean(SIGNED_IN_BEFORE) === true;
export const markSignedIn = (): void => storage.set(SIGNED_IN_BEFORE, true);

/**
 * Saves what was chosen before sign-up (path, state, quiz answers) to the
 * new account. Returns true when something was saved.
 */
export async function attachDraft(client: ApiClient): Promise<boolean> {
  const draft = getDraft();

  if (!draft.path && !draft.state && !draft.answers?.length) {
    return false;
  }

  if (draft.path || draft.state) {
    await client.PUT('/me/onboarding', { body: { path: draft.path ?? null, state: draft.state ?? null } });
  }

  if (draft.answers?.length) {
    await client.POST('/me/quiz-attempts', { body: { state: draft.state ?? null, answers: draft.answers } });
  }

  clearDraft();

  return true;
}

/** Strong (75%+), learning (40%+) or start here: the same rule as the server. */
export function topicLevels(results: { topic: QuizTopic; correct: boolean }[]): { topic: QuizTopic; level: TopicLevel }[] {
  const tally = new Map<QuizTopic, [number, number]>();

  for (const { topic, correct } of results) {
    const [right, total] = tally.get(topic) ?? [0, 0];
    tally.set(topic, [right + (correct ? 1 : 0), total + 1]);
  }

  return [...tally.entries()].map(([topic, [right, total]]) => {
    const share = right / Math.max(1, total);

    const level: TopicLevel = share >= 0.75 ? 'strong' : share >= 0.4 ? 'learning' : 'start_here';

    return { topic, level };
  });
}
