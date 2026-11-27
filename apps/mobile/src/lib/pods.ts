/**
 * Accountability pods and brokerage challenges on the phone (A13.2; agent
 * screen 131): the pod of five, weekly goals and shared numbers, the call and
 * pod messages; opt-in challenges with total and most-improved boards.
 */
import type { paths } from '@reos/api-client';

import { api } from '@/lib/api';

type Got<P extends keyof paths> = paths[P] extends { get: { responses: { 200: { content: { 'application/json': infer B } } } } } ? B : never;

export type PodView = Got<'/pod'>['data'];
export type Challenge = Got<'/challenges'>['data'][number];
export type ShareMetric = 'conversations' | 'appointments' | 'clients' | 'ce_hours';
export const SHARE_METRICS: ShareMetric[] = ['conversations', 'appointments', 'clients', 'ce_hours'];

const fail = (error: unknown): never => {
  const e = (error as { errors?: Record<string, string[]>; message?: string } | undefined) ?? {};
  throw new Error(Object.values(e.errors ?? {})[0]?.[0] ?? e.message ?? 'error');
};

export async function loadPod(): Promise<PodView | null> {
  const { data } = await api().GET('/pod');

  return data ? data.data : null;
}

export async function joinPod(): Promise<PodView> {
  const { data, error } = await api().POST('/pod/join');

  if (error || !data) return fail(error);

  return data.data;
}

/** Leaves; returns the pod to rejoin with Undo. */
export async function leavePod(): Promise<{ pod: PodView; left: string | null }> {
  const { data, error } = await api().POST('/pod/leave');

  if (error || !data) return fail(error);

  return { pod: data.data, left: data.left };
}

export async function rejoinPod(pod: string): Promise<PodView> {
  const { data, error } = await api().POST('/pod/rejoin', { body: { pod } });

  if (error || !data) return fail(error);

  return data.data;
}

export async function setPodGoal(text: string): Promise<PodView> {
  const { data, error } = await api().PUT('/pod/goal', { body: { text } });

  if (error || !data) return fail(error);

  return data.data;
}

export async function setPodShare(metrics: ShareMetric[]): Promise<PodView> {
  const { data, error } = await api().PUT('/pod/share', { body: { metrics } });

  if (error || !data) return fail(error);

  return data.data;
}

export async function loadChallenges(): Promise<Challenge[]> {
  const { data } = await api().GET('/challenges');

  return data ? data.data : [];
}

export async function joinChallenge(id: string, join = true): Promise<Challenge[]> {
  const { data, error } = join
    ? await api().POST('/challenges/{challenge}/join', { params: { path: { challenge: id } } })
    : await api().POST('/challenges/{challenge}/leave', { params: { path: { challenge: id } } });

  if (error || !data) return fail(error);

  return data.data;
}
