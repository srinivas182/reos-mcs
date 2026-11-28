/**
 * My files on the phone (B17.1, provider screens 14–15): deals agents
 * shared with their transaction coordinator, until closing.
 */
import type { paths } from '@reos/api-client';

import { api } from '@/lib/api';

type Got<P extends keyof paths> = paths[P] extends { get: { responses: { 200: { content: { 'application/json': infer B } } } } } ? B : never;

export type FilesBoard = Got<'/coordinator/files'>['data'];
export type FileCard = FilesBoard['files'][number];
export type FileDetail = Got<'/coordinator/files/{share}'>['data'];

const fail = (error: unknown): never => {
  const e = (error as { errors?: Record<string, string[]>; message?: string } | undefined) ?? {};
  throw new Error(Object.values(e.errors ?? {})[0]?.[0] ?? e.message ?? 'error');
};

export async function loadFiles(): Promise<FilesBoard | null> {
  const { data } = await api().GET('/coordinator/files');

  return data ? data.data : null;
}

/** Null once access ended (the deal closed, or the agent removed the coordinator). */
export async function loadFile(share: string): Promise<FileDetail | null> {
  const { data } = await api().GET('/coordinator/files/{share}', { params: { path: { share } } });

  return data ? data.data : null;
}

/** A document as `doc:<name>`, or a date's id. */
export async function tickItem(share: string, item: string, done: boolean): Promise<void> {
  const { error } = await api().PUT('/coordinator/files/{share}/checklist', { params: { path: { share } }, body: { item, done } });

  if (error) {
    fail(error);
  }
}

/** Goes to the coordinator's chat with the agent; returns the conversation. */
export async function messageAgent(share: string, body: string): Promise<string> {
  const { data, error } = await api().POST('/coordinator/files/{share}/messages', { params: { path: { share } }, body: { body } });

  return data ? data.data.conversation : fail(error);
}

/** "2 of 4 done" as a 0–1 share for a progress bar. */
export function progressOf(card: Pick<FileCard, 'progress'>): number {
  return card.progress.total === 0 ? 0 : Math.min(1, card.progress.done / card.progress.total);
}
