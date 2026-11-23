/**
 * Flashcards on the phone (candidate screen 31, A9.2d, ADR 0035): the whole
 * deck kept on the phone, reviewed fully offline with the same FSRS
 * schedule as the server, each review queued with its own id and synced
 * when the phone is back online (the server counts each once and stays the
 * record). Save as flashcard and the speed round live here too.
 */
import type { paths } from '@reos/api-client';
import * as Crypto from 'expo-crypto';
import { createMMKV } from 'react-native-mmkv';

import { api } from '@/lib/api';
import { review as schedule, type Rating } from '@/lib/fsrs';

type Got<P extends keyof paths> = paths[P] extends { get: { responses: { 200: { content: { 'application/json': infer B } } } } } ? B : never;
type Posted<P extends keyof paths> = paths[P] extends { post: { responses: { 200: { content: { 'application/json': infer B } } } } } ? B : never;

export type Deck = Got<'/practice/flashcards'>['data'];
export type Card = Deck['cards'][number];
export type SpeedRound = Posted<'/practice/speed'>['data'];
export type SpeedResult = Posted<'/practice/speed/{round}/finish'>['data'];
export type PendingReview = { card_id: number; rating: Rating; reviewed_at: string; client_id: string };

const store = createMMKV({ id: 'reos.flashcards' });
const DECK = 'deck';
const QUEUE = 'queue';

function read<T>(name: string, fallback: T): T {
  const raw = store.getString(name);
  if (raw === undefined) return fallback;
  try {
    return JSON.parse(raw) as T;
  } catch {
    return fallback;
  }
}
const write = (name: string, value: unknown) => store.set(name, JSON.stringify(value));

/** Reviews waiting to sync. */
export const pendingReviews = (): PendingReview[] => read<PendingReview[]>(QUEUE, []);

/** The deck as kept on the phone (null before the first download). */
export const savedDeck = (): Deck | null => read<Deck | null>(DECK, null);

/** Sends the queue; reviews the server has (applied now or before) leave it. False while offline. */
export async function syncReviews(): Promise<boolean> {
  const queue = pendingReviews();
  if (queue.length === 0) return true;
  const { data, error } = await api()
    .POST('/practice/flashcards/reviews', { body: { reviews: queue } })
    .catch(() => ({ data: undefined, error: true }));
  if (error || !data) return false;
  const sent = new Set(queue.map((r) => r.client_id));
  write(
    QUEUE,
    pendingReviews().filter((r) => !sent.has(r.client_id)),
  );

  return true;
}

/** The deck: synced and fresh from the server when online, else the phone's copy. `topic` keeps to one topic. */
export async function loadDeck(topic: string | null = null): Promise<{ deck: Deck | null; offline: boolean }> {
  const synced = await syncReviews();
  const { data } = synced ? await api().GET('/practice/flashcards', { params: { query: topic ? { topic } : {} } }).catch(() => ({ data: undefined })) : { data: undefined };

  if (data?.data) {
    if (topic === null) write(DECK, data.data);

    return { deck: data.data, offline: false };
  }

  const saved = savedDeck();

  return { deck: saved && topic ? { ...saved, cards: saved.cards.filter((c) => c.topic === topic) } : saved, offline: true };
}

/** What a burst works through: cards due, oldest first, then today's new cards. */
export function burstQueue(deck: Deck, now = Date.now()): Card[] {
  const due = deck.cards.filter((c) => !c.new && c.due_at !== null && new Date(c.due_at).getTime() <= now).sort((a, b) => (a.due_at ?? '').localeCompare(b.due_at ?? ''));

  return [...due, ...deck.cards.filter((c) => c.new).slice(0, deck.new_left)];
}

/** Rate a card: scheduled on the phone at once, queued, and synced when it can be. */
export function rate(card: Card, rating: Rating, at = new Date()): Card {
  const next = schedule(card, rating, at);
  const updated: Card = { ...card, ...next, new: false };
  write(QUEUE, [...pendingReviews(), { card_id: card.id, rating, reviewed_at: at.toISOString(), client_id: Crypto.randomUUID() }]);
  const deck = savedDeck();
  if (deck) write(DECK, { ...deck, cards: deck.cards.map((c) => (c.id === card.id ? updated : c)) });
  void syncReviews();

  return updated;
}

/** Save as flashcard: from a check-in, an Ask answer, or answer review. */
export async function saveFlashcard(source: 'checkin' | 'ask' | 'review', front: string, back: string, questionId?: number): Promise<boolean> {
  const { data } = await api()
    .POST('/practice/flashcards', { body: { source, front, back, ...(questionId ? { question_id: questionId } : {}) } })
    .catch(() => ({ data: undefined }));

  return Boolean(data?.data);
}

export async function startSpeedRound(): Promise<SpeedRound | null> {
  const { data } = await api().POST('/practice/speed');

  return data?.data ?? null;
}

export async function finishSpeedRound(round: number, taps: { id: number; true: boolean }[]): Promise<SpeedResult | null> {
  const { data } = await api().POST('/practice/speed/{round}/finish', { params: { path: { round } }, body: { taps } });

  return data?.data ?? null;
}
