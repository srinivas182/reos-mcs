/**
 * Feedback from agents on the phone (A13.3b; agent screen 125): rate the
 * other side's agent after a verified closing, and see your own feedback
 * once three agents have rated you.
 */
import type { paths } from '@reos/api-client';

import { api } from '@/lib/api';

type Got<P extends keyof paths> = paths[P] extends { get: { responses: { 200: { content: { 'application/json': infer B } } } } } ? B : never;

export type FeedbackView = Got<'/feedback'>['data'];
export type ToRate = FeedbackView['to_rate'][number];
export type FeedbackItem = FeedbackView['mine']['items'][number];
export type Dimension = 'communication' | 'responsiveness' | 'professionalism';
export const DIMENSIONS: Dimension[] = ['communication', 'responsiveness', 'professionalism'];

const fail = (error: unknown): never => {
  const e = (error as { errors?: Record<string, string[]>; message?: string } | undefined) ?? {};
  throw new Error(Object.values(e.errors ?? {})[0]?.[0] ?? e.message ?? 'error');
};

export async function loadFeedback(): Promise<FeedbackView | null> {
  const { data } = await api().GET('/feedback');

  return data ? data.data : null;
}

export async function rateAgent(row: ToRate, scores: Record<Dimension, number>, comment: string): Promise<FeedbackView> {
  const { data, error } = await api().POST('/feedback', { body: { deal: row.deal, agent: row.agent.id, ...scores, comment: comment.trim() === '' ? null : comment.trim() } });

  if (error || !data) return fail(error);

  return data.data;
}

export async function respondToFeedback(id: string, response: string): Promise<FeedbackView> {
  const { data, error } = await api().PUT('/feedback/{feedback}/response', { params: { path: { feedback: id } }, body: { response } });

  if (error || !data) return fail(error);

  return data.data;
}

/** Reports a rating; `null` takes the report back (Undo). */
export async function reportFeedback(id: string, reason: string | null): Promise<FeedbackView> {
  const { data, error } = await api().POST('/feedback/{feedback}/report', { params: { path: { feedback: id } }, body: { reason } });

  if (error || !data) return fail(error);

  return data.data;
}

export async function showFeedbackPublicly(value: boolean): Promise<FeedbackView> {
  const { data, error } = await api().PUT('/feedback/visibility', { body: { public: value } });

  if (error || !data) return fail(error);

  return data.data;
}
