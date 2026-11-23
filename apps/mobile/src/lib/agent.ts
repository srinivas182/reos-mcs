/**
 * Agent modes on the phone (A11.1a; agent screens 4, 5, 6, 146): the goal
 * that sets Today, work-style flags and suggested switches.
 */
import type { paths } from '@reos/api-client';
import { useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';

import { api } from '@/lib/api';

type Got<P extends keyof paths> = paths[P] extends { get: { responses: { 200: { content: { 'application/json': infer B } } } } } ? B : never;

export type ModeState = Got<'/agent/mode'>['data'];
export type Goal = 'launch' | 'grow' | 'lead' | 'own';
export type Flag = 'part_time' | 'bilingual' | 'specialist' | 'referral_only' | 'returning';
export const GOALS: Goal[] = ['launch', 'grow', 'lead', 'own'];
export const FLAGS: Flag[] = ['part_time', 'bilingual', 'specialist', 'referral_only', 'returning'];

const fail = (error: unknown): never => {
  const e = (error as { errors?: Record<string, string[]>; message?: string } | undefined) ?? {};
  throw new Error(Object.values(e.errors ?? {})[0]?.[0] ?? e.message ?? 'error');
};

const take = ({ data, error }: { data?: { data: ModeState }; error?: unknown }): ModeState => (data ? data.data : fail(error));

export const setGoal = async (goal: Goal, reason: 'setup' | 'manual' | 'suggested') => take(await api().PUT('/agent/mode', { body: { goal, reason } }));
export const setFlags = async (flags: Flag[]) => take(await api().PUT('/agent/mode/flags', { body: { flags } }));
export const snooze = async () => take(await api().POST('/agent/mode/snooze'));

/** The agent's mode, loaded when the screen comes into view; null for other roles. */
export function useMode(enabled = true) {
  const [mode, setMode] = useState<ModeState | null>(null);

  useFocusEffect(
    useCallback(() => {
      if (!enabled) return;
      void api()
        .GET('/agent/mode')
        .then(({ data }) => setMode(data?.data ?? null))
        .catch(() => undefined);
    }, [enabled]),
  );

  return { mode, setMode };
}
