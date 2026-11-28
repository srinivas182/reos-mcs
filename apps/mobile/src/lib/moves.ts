/**
 * Joining another brokerage or team from the phone (A14.3, ADR 0063): an
 * invite code, what moves with the agent, then join.
 */
import type { paths } from '@reos/api-client';
import type { TFunction } from 'i18next';

import { api } from '@/lib/api';

type Posted<P extends keyof paths> = paths[P] extends { post: { responses: { 200: { content: { 'application/json': infer B } } } } } ? B : never;

export type MovePreview = Posted<'/me/moves/preview'>['data'];
export type Move = NonNullable<MovePreview['move']>;

const fail = (error: unknown): never => {
  const e = (error as { errors?: Record<string, string[]>; message?: string } | undefined) ?? {};
  throw new Error(Object.values(e.errors ?? {})[0]?.[0] ?? e.message ?? 'error');
};

/** Codes are shown as ABCD-EFGH; people type them any way. */
export function cleanCode(code: string): string {
  const plain = code.toUpperCase().replace(/[^A-Z0-9]/g, '');

  return plain.length === 8 ? `${plain.slice(0, 4)}-${plain.slice(4)}` : plain;
}

export async function previewMove(code: string): Promise<MovePreview> {
  const { data, error } = await api().POST('/me/moves/preview', { body: { code: cleanCode(code) } });

  return data ? data.data : fail(error);
}

export async function joinWithCode(code: string, confirm: boolean): Promise<string> {
  const { data, error } = await api().POST('/me/moves', { body: { code: cleanCode(code), confirm } });

  return data ? data.data.organisation : fail(error);
}

/** One line per row of what moves, stays or ends. */
export function moveLines(rows: Move['moves'] | Move['stays'] | Move['ends'], t: TFunction): string[] {
  return rows.map((row) => t(`moves.keys.${row.key}`, { count: row.count }));
}
