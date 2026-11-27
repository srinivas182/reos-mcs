/**
 * The candidate studio on the phone (B13.3): free study-streak,
 * practice-score and exam-pass posts, checked so nothing advertises
 * services before the license is active.
 */
import type { paths } from '@reos/api-client';

import { api } from '@/lib/api';

type Got<P extends keyof paths> = paths[P] extends { get: { responses: { 200: { content: { 'application/json': infer B } } } } } ? B : never;
type Posted<P extends keyof paths> = paths[P] extends { post: { responses: { 200: { content: { 'application/json': infer B } } } } } ? B : never;

export type Moment = Got<'/candidate-studio'>['data'][number];
export type MadePost = Posted<'/candidate-studio/moments/{milestone}'>['data'];
export type CheckResult = Posted<'/candidate-studio/check'>['data'];

const fail = (error: unknown): never => {
  const e = (error as { errors?: Record<string, string[]>; message?: string } | undefined) ?? {};
  throw new Error(Object.values(e.errors ?? {})[0]?.[0] ?? e.message ?? 'error');
};

export async function loadMoments(): Promise<Moment[]> {
  const { data } = await api().GET('/candidate-studio');

  return data ? data.data : [];
}

export async function makePost(milestone: Moment['milestone']): Promise<MadePost> {
  const { data, error } = await api().POST('/candidate-studio/moments/{milestone}', { params: { path: { milestone } } });

  return data ? data.data : fail(error);
}

export async function checkCaption(text: string): Promise<CheckResult> {
  const { data, error } = await api().POST('/candidate-studio/check', { body: { text } });

  return data ? data.data : fail(error);
}

export async function postIt(id: string, caption: number, design: number, text: string, targets: { connection: number; channel: string; id: string }[]): Promise<void> {
  const { error } = await api().POST('/candidate-studio/posts/{post}', { params: { path: { post: id } }, body: { caption, design, text, targets } });

  if (error) fail(error);
}
