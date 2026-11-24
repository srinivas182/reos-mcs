/**
 * The content studio's maker on the phone (B12.1; agent screens 165, 166
 * and 107): sources, making, versions, tones; and Fix automatically.
 * Videos (B12.2; screen 168): make, edit, hooks.
 */
import type { paths } from '@reos/api-client';

import { api } from '@/lib/api';

type Got<P extends keyof paths> = paths[P] extends { get: { responses: { 200: { content: { 'application/json': infer B } } } } } ? B : never;

export type StudioHome = Got<'/studio'>['data'];
export type Generation = Got<'/studio/generations/{generation}'>['data'];
/** Where a making can start; celebrations are made by REOS (B12.3). */
export type SourceType = Exclude<Generation['source']['type'], 'celebration'>;
export type Output = Generation['outputs'][number];
export type Retone = 'shorter' | 'professional' | 'spanish';
export type Video = Got<'/studio/videos/{video}'>['data'];
export type Scene = Video['scenes'][number];
export type Track = Video['music'];
export type SceneEdit = { media: string; start: number; seconds: number; caption: string };
export type VideoEdits = { title?: string; music?: Track; brand?: boolean; scenes?: SceneEdit[]; fix?: boolean; render?: boolean };

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

export async function loadVideo(id: string): Promise<Video | null> {
  const { data } = await api().GET('/studio/videos/{video}', { params: { path: { video: id } } });

  return data ? data.data : null;
}

export async function makeVideo(source: { type: 'listing' | 'own'; id?: string | null; text?: string | null }, clips: string[] = []): Promise<Video> {
  const { data, error } = await api().POST('/studio/videos', { body: { source, clips } });

  return data ? data.data : fail(error);
}

export async function editVideo(id: string, edits: VideoEdits): Promise<Video> {
  const { data, error } = await api().PUT('/studio/videos/{video}', { params: { path: { video: id } }, body: edits });

  return data ? data.data : fail(error);
}

export async function videoHooks(id: string): Promise<string[]> {
  const { data, error } = await api().POST('/studio/videos/{video}/hooks', { params: { path: { video: id } } });

  return data ? data.data : fail(error);
}

/** A video's scenes as edits, for changing one and sending them all. */
export const sceneEdits = (scenes: Scene[]): SceneEdit[] => scenes.map((s) => ({ media: s.media, start: s.start, seconds: s.seconds, caption: s.caption }));
