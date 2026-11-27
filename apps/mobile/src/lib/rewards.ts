/**
 * Daily goals, points and rewards on the phone (A13.3a; agent screens 111
 * and 112): today's goals and streak, the balance and what points pay for.
 */
import type { paths } from '@reos/api-client';

import { api } from '@/lib/api';

type Got<P extends keyof paths> = paths[P] extends { get: { responses: { 200: { content: { 'application/json': infer B } } } } } ? B : never;

export type Today = Got<'/goals/today'>['data'];
export type RewardsSummary = Got<'/rewards'>['data'];
export type RewardOption = RewardsSummary['options'][number];
export type HistoryRow = RewardsSummary['history'][number];

const fail = (error: unknown): never => {
  const e = (error as { errors?: Record<string, string[]>; message?: string } | undefined) ?? {};
  throw new Error(Object.values(e.errors ?? {})[0]?.[0] ?? e.message ?? 'error');
};

export async function loadToday(): Promise<Today | null> {
  const { data } = await api().GET('/goals/today');

  return data ? data.data : null;
}

export async function loadRewards(): Promise<RewardsSummary | null> {
  const { data } = await api().GET('/rewards');

  return data ? data.data : null;
}

export async function spendPoints(price: number): Promise<RewardsSummary> {
  const { data, error } = await api().POST('/rewards/redeem', { body: { price } });

  if (error || !data) return fail(error);

  return data.data;
}

/** The ledger line in words: which goal, a bonus, a session, points used. */
export type ReasonKey = 'rewards.reasons.returned' | 'rewards.reasons.all_done' | 'rewards.reasons.goal' | 'rewards.reasons.bonus' | 'rewards.reasons.session' | 'rewards.reasons.order' | 'rewards.reasons.other';

export function reasonKey(row: HistoryRow): { key: ReasonKey; goal?: string } {
  const [kind, , third, fourth] = (row.memo ?? '').split(':');

  if (row.reason === 'points_returned') return { key: 'rewards.reasons.returned' };
  if (kind === 'goal') return third === 'all_done' ? { key: 'rewards.reasons.all_done' } : { key: 'rewards.reasons.goal', goal: third };
  if (kind === 'campaign') return { key: 'rewards.reasons.bonus', goal: fourth };
  if (kind === 'session') return { key: 'rewards.reasons.session' };
  if (kind === 'order') return { key: 'rewards.reasons.order' };

  return { key: 'rewards.reasons.other' };
}
