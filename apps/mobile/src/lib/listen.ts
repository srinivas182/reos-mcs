/**
 * Audio-only lessons and course picks on the phone (A14.2, ADR 0062). The
 * phone reads the lesson aloud; check-ins are asked out loud and answered
 * by voice (heard on the phone) or a tap. Heartbeats say `mode: audio`, so
 * seat time counts with the screen off — where the course allows it.
 */
import type { paths } from '@reos/api-client';

import { api } from '@/lib/api';

type Got<P extends keyof paths> = paths[P] extends { get: { responses: { 200: { content: { 'application/json': infer B } } } } } ? B : never;

export type Listening = Got<'/learn/lessons/{lesson}/listen'>['data'];
export type CoursePick = Got<'/learn/picks'>['data'][number];
export type ListenState = Listening['player'];

export async function loadListening(lesson: number): Promise<Listening | null> {
  const { data } = await api().GET('/learn/lessons/{lesson}/listen', { params: { path: { lesson } } });

  return data ? data.data : null;
}

export async function loadPicks(): Promise<CoursePick[]> {
  const { data } = await api().GET('/learn/picks', {});

  return data ? data.data : [];
}

export async function audioBeat(lesson: number, body: { position: number; playing: boolean; visible: boolean; session: string; timezone: string | null }): Promise<ListenState | null> {
  const { data } = await api()
    .POST('/learn/lessons/{lesson}/heartbeat', { params: { path: { lesson } }, body: { ...body, mode: 'audio' } })
    .catch(() => ({ data: undefined }));

  return data ? data.data : null;
}

export async function answerAloud(lesson: number, key: string, choice: number | null) {
  const { data } = await api().POST('/learn/lessons/{lesson}/checkins', { params: { path: { lesson } }, body: { key, choice } });

  return data ?? null;
}

const NUMBERS: Record<string, number> = {
  one: 1,
  first: 1,
  won: 1,
  two: 2,
  second: 2,
  to: 2,
  too: 2,
  three: 3,
  third: 3,
  four: 4,
  fourth: 4,
  for: 4,
  five: 5,
  fifth: 5,
  six: 6,
  sixth: 6,
  uno: 1,
  una: 1,
  primera: 1,
  primero: 1,
  dos: 2,
  segunda: 2,
  segundo: 2,
  tres: 3,
  tercera: 3,
  tercero: 3,
  cuatro: 4,
  cuarta: 4,
  cuarto: 4,
  cinco: 5,
  quinta: 5,
  seis: 6,
  sexta: 6,
};

/**
 * Which option the learner said (0-based), from "two", "option 3", "the
 * second one", "B" or the option's own words; null when unclear.
 */
export function choiceFromSpeech(said: string, options: string[]): number | null {
  const text = said
    .toLowerCase()
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .replace(/[^\p{L}\p{N}\s]/gu, ' ')
    .trim();
  if (text === '') return null;
  const words = text.split(/\s+/);

  for (const word of words) {
    const n = /^\d+$/.test(word) ? Number(word) : (NUMBERS[word] ?? (word.length === 1 && word >= 'a' && word <= 'f' ? word.charCodeAt(0) - 96 : undefined));
    if (n !== undefined && n >= 1 && n <= options.length) return n - 1;
  }

  const plain = (s: string) =>
    s
      .toLowerCase()
      .normalize('NFD')
      .replace(/[̀-ͯ]/g, '')
      .replace(/[^\p{L}\p{N}\s]/gu, ' ')
      .trim();
  const match = options.findIndex((option) => plain(option) !== '' && text.includes(plain(option)));

  return match >= 0 ? match : null;
}

/** Text in pieces short enough for the phone's speech (about 1,500 characters), split at sentences. */
export function speakable(text: string, max = 1500): string[] {
  const sentences =
    text
      .replace(/\s+/g, ' ')
      .trim()
      .match(/[^.!?]+[.!?]*\s*/g) ?? [];
  const pieces: string[] = [];
  let now = '';

  for (const sentence of sentences) {
    if (now !== '' && (now + sentence).length > max) {
      pieces.push(now.trim());
      now = '';
    }
    now += sentence.length > max ? `${sentence.slice(0, max)} ` : sentence;
  }
  if (now.trim() !== '') pieces.push(now.trim());

  return pieces;
}
