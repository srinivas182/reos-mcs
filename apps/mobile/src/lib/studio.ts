/**
 * The content studio's maker on the phone (B12.1; agent screens 165, 166
 * and 107): sources, making, versions, tones; and Fix automatically.
 */
import type { paths } from '@reos/api-client';

import { api } from '@/lib/api';

type Got<P extends keyof paths> = paths[P] extends { get: { responses: { 200: { content: { 'application/json': infer B } } } } } ? B : never;

export type StudioHome = Got<'/studio'>['data'];
export type Generation = Got<'/studio/generations/{generation}'>['data'];
export type SourceType = Generation['source']['type'];
export type Output = Generation['outputs'][number];
export type Retone = 'shorter' | 'professional' | 'spanish';

const fail = (error: unknown): never => {
  const e = (error as { errors?: Record<string, string[]>; message?: string } | undefined) ?? {};
  throw new Error(Object.values(e.errors ?? {})[0]?.[0] ?? e.message ?? 'error');
};

export async function loadStudio(): Promise<StudioHome | null> {
  const { data } = await api().GET('/studio');

  return data ? data.data : null;
}

export async function loadGeneration(id: string): Promise<Generation | null> {
  const { data } = await api().GET('/studio/generations/{generation}', { params: { path: { generation: id } } });

  return data ? data.data : null;
}

export async function makeContent(source: { type: SourceType; id?: string | null; text?: string | null; city?: string | null }, outputs: Output[]): Promise<Generation> {
  const { data, error } = await api().POST('/studio/generations', { body: { source, outputs } });

  return data ? data.data : fail(error);
}

export async function retoneCaption(id: string, index: number, tone: Retone): Promise<Generation> {
  const { data, error } = await api().POST('/studio/generations/{generation}/captions', { params: { path: { generation: id } }, body: { index, tone } });

  return data ? data.data : fail(error);
}

export async function fixPost(body: string): Promise<string> {
  const { data, error } = await api().POST('/social/posts/fix', { body: { body } });

  return data ? data.data.body : fail(error);
}
