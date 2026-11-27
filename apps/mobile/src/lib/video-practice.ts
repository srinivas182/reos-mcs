/**
 * Video practice on the phone (A14.1b, ADR 0061). The camera records up to
 * two minutes; the video stays on the phone. The words are recognised on
 * the phone from the recording, the agent corrects them, and only the
 * words and the length go to REOS for the review.
 */
import type { paths } from '@reos/api-client';
import type { TFunction } from 'i18next';

import { api } from '@/lib/api';

type Got<P extends keyof paths> = paths[P] extends { get: { responses: { 200: { content: { 'application/json': infer B } } } } } ? B : never;

export type VideoOverview = Got<'/video-practice'>['data'];
export type VideoReview = Got<'/video-practice/{videoPractice}'>['data'];
export type VideoKind = VideoOverview['kinds'][number];

/** The compliance engine's rule codes (B13.1), for their names. */
export type ComplianceCode = 'broker' | 'broker_size' | 'team_name' | 'links' | 'claims' | 'title' | 'fair_housing' | 'unclear' | 'banned' | 'unlicensed';

export async function loadVideoPractice(): Promise<VideoOverview | null> {
  const { data } = await api().GET('/video-practice');

  return data ? data.data : null;
}

export async function loadReview(id: string): Promise<VideoReview | null> {
  const { data } = await api().GET('/video-practice/{videoPractice}', { params: { path: { videoPractice: id } } });

  return data ? data.data : null;
}

export async function requestReview(kind: VideoKind, transcript: string, seconds: number): Promise<VideoReview> {
  const { data, error } = await api().POST('/video-practice', { body: { kind, transcript, duration_seconds: seconds } });

  if (data) return data.data;
  const e = (error as { errors?: Record<string, string[]>; message?: string } | undefined) ?? {};
  throw new Error(Object.values(e.errors ?? {})[0]?.[0] ?? e.message ?? 'error');
}

/** Seconds as m:ss for the recording timer. */
export function clock(seconds: number): string {
  const s = Math.max(0, Math.floor(seconds));

  return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
}

/** The filler words as one line, most used first, or the "none" line. */
export function fillerLine(fillers: VideoReview['fillers'], t: TFunction): string {
  if (fillers.length === 0) return t('videopractice.noFillers');

  return fillers.map((f) => t('videopractice.fillerCount', { word: f.word, count: f.count })).join(' · ');
}
