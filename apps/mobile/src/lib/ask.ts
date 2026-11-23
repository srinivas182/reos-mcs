/**
 * Ask, the study mate (A10.1, candidate screen 13): conversations with
 * answers from the person's courses first.
 */
import type { paths } from '@reos/api-client';

import { api } from '@/lib/api';

type Got<P extends keyof paths> = paths[P] extends { get: { responses: { 200: { content: { 'application/json': infer B } } } } } ? B : never;

export type AskThread = Got<'/ask/threads/{thread}'>['data'];
export type AskMessage = AskThread['messages'][number];
export type AskThreadRow = Got<'/ask/threads'>['data'][number];
export type AskPackage = Got<'/ask/package'>['data'];
export type AskMode = 'ask' | 'simpler' | 'spanish' | 'quiz';

export async function loadThreads(): Promise<AskThreadRow[]> {
  const { data } = await api().GET('/ask/threads');

  return data?.data ?? [];
}

export async function loadThread(uuid: string): Promise<AskThread | null> {
  const { data } = await api().GET('/ask/threads/{thread}', { params: { path: { thread: uuid } } });

  return data?.data ?? null;
}

export async function loadPackage(): Promise<AskPackage | null> {
  const { data } = await api().GET('/ask/package');

  return data?.data ?? null;
}

/** A question, or a follow-up on the last answer. Throws the server's message when it refuses. */
export async function ask(mode: AskMode, question: string | null, thread: string | null): Promise<AskThread> {
  const { data, error } = await api().POST('/ask', { body: { mode, question, thread } });

  if (!data) {
    const errors = (error as { errors?: Record<string, string[]>; message?: string } | undefined) ?? {};
    throw new Error(Object.values(errors.errors ?? {})[0]?.[0] ?? errors.message ?? 'error');
  }

  return data.data;
}

/** "3:20" for a lesson second. */
export const clock = (seconds: number): string => `${Math.floor(seconds / 60)}:${String(Math.floor(seconds % 60)).padStart(2, '0')}`;
