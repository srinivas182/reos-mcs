/**
 * Offline lessons on the phone (A8.2b, ADR 0030; Candidate App Journey
 * screen 34, Downloads). A saved lesson is the 720p video in the app's own
 * storage (not visible to other apps), with everything the player needs, and
 * a key kept in the Keychain or Keystore. It works for 30 days between online
 * checks, and only while the learner still has the course.
 *
 * Seat time watched offline is kept as stretches on the phone, signed with
 * the lesson's key, and sent when the phone is back online. The server checks
 * it against the same rules as online and counts each stretch once.
 */
import type { paths } from '@reos/api-client';
import * as Crypto from 'expo-crypto';
import { Directory, File, Paths } from 'expo-file-system';
import * as Network from 'expo-network';
import * as SecureStore from 'expo-secure-store';
import { createMMKV } from 'react-native-mmkv';

import { api } from '@/lib/api';
import type { LearnLesson } from '@/lib/learn';
import type { PlayerState } from '@/lib/player';

type Created<P extends keyof paths> = paths[P] extends { post: { responses: { 200: { content: { 'application/json': infer B } } } } } ? B : never;

export type OfflineData = Omit<Created<'/learn/lessons/{lesson}/downloads'>['data'], 'key' | 'url'>;
export type Checkpoint = OfflineData['checkpoints'][number];

export type SavedLesson = {
  grant: number;
  lessonId: number;
  title: string;
  course: string;
  bytes: number;
  uri: string;
  expiresAt: string;
  savedAt: string;
  data: OfflineData;
};

export type OfflineEvent =
  | { id: string; type: 'watch'; at: string; seconds: number }
  | { id: string; type: 'checkin'; at: string; key: string; choice: number | null };

export type DownloadSettings = { limitGb: 1 | 2 | 5; smart: boolean; wifiOnly: boolean };

export const LIMITS = [1, 2, 5] as const;

/** The server counts at most 15 minutes a stretch; the phone starts a new one before that. */
const MAX_STRETCH = 900;
/** A tick later than this after the stretch's end starts a new stretch (the time between doesn't count). */
const JOIN = 20;
const GB = 1024 * 1024 * 1024;

const store = createMMKV({ id: 'reos.downloads' });
const SAVED = 'saved';
const SETTINGS = 'settings';
const events = (grant: number) => `events.${grant}`;
const keyName = (grant: number) => `reos.offline.key.${grant}`;

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

export function downloadSettings(): DownloadSettings {
  return { limitGb: 2, smart: true, wifiOnly: true, ...read<Partial<DownloadSettings>>(SETTINGS, {}) };
}

export function setDownloadSettings(change: Partial<DownloadSettings>): DownloadSettings {
  const next = { ...downloadSettings(), ...change };
  write(SETTINGS, next);

  return next;
}

export function savedLessons(): SavedLesson[] {
  return read<SavedLesson[]>(SAVED, []);
}

export function savedFor(lessonId: number): SavedLesson | undefined {
  return savedLessons().find((s) => s.lessonId === lessonId);
}

export function usedBytes(): number {
  return savedLessons().reduce((sum, s) => sum + s.bytes, 0);
}

/** Saved, not past its 30 days, and the file is still there. */
export function playable(saved: SavedLesson, now = new Date()): boolean {
  return new Date(saved.expiresAt) > now && new File(saved.uri).exists;
}

function keep(saved: SavedLesson[]) {
  write(SAVED, saved);
}

function update(grant: number, change: (s: SavedLesson) => SavedLesson) {
  keep(savedLessons().map((s) => (s.grant === grant ? change(s) : s)));
}

/** The lesson as the player shows it offline: the saved copy, playing the file on the phone. */
export function offlineLesson(saved: SavedLesson): LearnLesson {
  const { lesson, player } = saved.data;

  return { ...lesson, video: lesson.video ? { ...lesson.video, type: 'file', url: saved.uri } : null, player };
}

export class SaveError extends Error {
  constructor(public readonly reason: 'wifi' | 'limit' | 'server', message?: string) {
    super(message ?? reason);
  }
}

export async function onWifi(): Promise<boolean> {
  const state = await Network.getNetworkStateAsync();

  return state.type === Network.NetworkStateType.WIFI;
}

/**
 * Save a lesson on this phone: on Wi-Fi only unless the learner turned that
 * off, and within the storage limit they chose.
 */
export async function saveLesson(lessonId: number): Promise<SavedLesson> {
  const existing = savedFor(lessonId);
  if (existing && playable(existing)) return existing;

  const settings = downloadSettings();
  if (settings.wifiOnly && !(await onWifi())) throw new SaveError('wifi');

  const { data, error } = await api().POST('/learn/lessons/{lesson}/downloads', { params: { path: { lesson: lessonId } } });
  if (!data) throw new SaveError('server', (error as { message?: string } | undefined)?.message);

  const { key, url, ...offline } = data.data;
  const bytes = offline.bytes ?? 0;
  if (usedBytes() + bytes > settings.limitGb * GB) {
    await api().DELETE('/learn/downloads/{grant}', { params: { path: { grant: offline.grant } } });
    throw new SaveError('limit');
  }

  const folder = new Directory(Paths.document, 'lessons');
  if (!folder.exists) folder.create();
  const file = await File.downloadFileAsync(url, new File(folder, `${offline.grant}.mp4`), { idempotent: true });
  await SecureStore.setItemAsync(keyName(offline.grant), key, { keychainAccessible: SecureStore.WHEN_UNLOCKED_THIS_DEVICE_ONLY });

  const saved: SavedLesson = {
    grant: offline.grant,
    lessonId,
    title: offline.lesson.title,
    course: offline.lesson.course.title,
    bytes: bytes || file.size,
    uri: file.uri,
    expiresAt: offline.expires_at,
    savedAt: new Date().toISOString(),
    data: offline,
  };
  keep([...savedLessons().filter((s) => s.lessonId !== lessonId), saved]);

  return saved;
}

/** Remove a saved lesson: its file, its key and (when online) the server's grant. Unsent offline time is sent first. */
export async function removeLesson(grant: number, tellServer = true): Promise<void> {
  const saved = savedLessons().find((s) => s.grant === grant);

  if (tellServer) {
    await flush(grant).catch(() => null);
    await api()
      .DELETE('/learn/downloads/{grant}', { params: { path: { grant } } })
      .catch(() => null);
  }

  if (saved) {
    const file = new File(saved.uri);
    if (file.exists) file.delete();
  }

  await SecureStore.deleteItemAsync(keyName(grant));
  keep(savedLessons().filter((s) => s.grant !== grant));
}

/**
 * Online: send unsent offline time, then check every saved lesson (the
 * 30-day check). Lessons whose access ended are removed; with smart
 * downloads on, so are lessons already completed.
 *
 * @returns the lessons removed
 */
export async function syncDownloads(): Promise<SavedLesson[]> {
  await flushAll();
  const { data } = await api().GET('/learn/downloads');
  if (!data) return [];

  const removed: SavedLesson[] = [];
  const settings = downloadSettings();

  for (const saved of savedLessons()) {
    const server = data.data.find((d) => d.grant === saved.grant);

    if (!server || server.status === 'revoked' || (settings.smart && saved.data.player.completed)) {
      removed.push(saved);
      await removeLesson(saved.grant, server?.status === 'active');
    } else {
      update(saved.grant, (s) => ({ ...s, expiresAt: server.expires_at }));
    }
  }

  return removed;
}

/**
 * Smart downloads: the next two lessons, on Wi-Fi only, when the learner
 * turned the switch on. Failures are quiet; Downloads shows what's saved.
 */
export async function smartDownload(next: number | null): Promise<void> {
  const settings = downloadSettings();
  if (!settings.smart || next === null || !(await onWifi())) return;

  let lesson: number | null = next;
  for (let i = 0; i < 2 && lesson !== null; i++) {
    const saved: SavedLesson | null = await saveLesson(lesson).catch(() => null);
    lesson = saved?.data.lesson.next ?? null;
  }
}

/* ---------- Offline seat time ---------- */

export function pending(grant: number): OfflineEvent[] {
  return read<OfflineEvent[]>(events(grant), []);
}

/**
 * A player tick while offline, the video playing and REOS in front: extends
 * the current stretch to now, or starts a new one (the time before a new
 * stretch doesn't count, as online).
 */
export function recordWatch(grant: number, now = new Date()): void {
  const list = pending(grant);
  const last = list[list.length - 1];

  if (last?.type === 'watch') {
    const end = new Date(last.at).getTime() + last.seconds * 1000;
    const gap = Math.round((now.getTime() - end) / 1000);

    if (gap >= 0 && gap <= JOIN && last.seconds + gap <= MAX_STRETCH) {
      write(events(grant), [...list.slice(0, -1), { ...last, seconds: last.seconds + gap }]);

      return;
    }
  }

  write(events(grant), [...list, { id: Crypto.randomUUID(), type: 'watch', at: now.toISOString(), seconds: 0 }]);
}

export function recordCheckin(grant: number, key: string, choice: number | null, now = new Date()): void {
  write(events(grant), [...pending(grant), { id: Crypto.randomUUID(), type: 'checkin', at: now.toISOString(), key, choice }]);
}

/** What the phone shows offline: the server's last word plus what's waiting to be sent. */
export function offlineState(saved: SavedLesson): PlayerState {
  const list = pending(saved.grant);
  const base = saved.data.player;
  const answered = new Set([...saved.data.answered, ...list.flatMap((e) => (e.type === 'checkin' ? [e.key] : []))]);
  const watched = list.reduce((sum, e) => sum + (e.type === 'watch' ? e.seconds : 0), 0);
  const next = saved.data.checkpoints.find((c) => !answered.has(c.key));
  const counted = Math.min(base.counted + watched, next ? Math.max(base.counted, next.at) : Number.MAX_SAFE_INTEGER);
  const checkin = next && counted >= next.at ? { key: next.key, at: next.at, kind: next.kind, question: next.question } : null;

  return {
    ...base,
    counted,
    percent: Math.min(100, Math.floor((100 * counted) / Math.max(1, base.required))),
    reason: checkin ? 'checkin_due' : null,
    checkin,
  };
}

/** Send a saved lesson's offline time, signed with its key; the server's state comes back. */
export async function flush(grant: number): Promise<PlayerState | null> {
  const list = pending(grant);
  const key = await SecureStore.getItemAsync(keyName(grant));
  if (list.length === 0 || key === null) return null;

  const payload = JSON.stringify(list);
  const { data } = await api().POST('/learn/downloads/{grant}/seat-time', {
    params: { path: { grant } },
    body: { payload, signature: await hmacSha256Hex(key, payload), timezone: Intl.DateTimeFormat().resolvedOptions().timeZone ?? null },
  });
  if (!data) return null;

  // Only what was sent is cleared: a stretch recorded meanwhile stays for next time.
  const sent = new Set(list.map((e) => e.id));
  write(
    events(grant),
    pending(grant).filter((e) => !sent.has(e.id)),
  );
  const answered = list.flatMap((e) => (e.type === 'checkin' ? [e.key] : []));
  update(grant, (s) => ({ ...s, data: { ...s.data, player: data.state, answered: [...new Set([...s.data.answered, ...answered])] } }));

  return data.state;
}

export async function flushAll(): Promise<void> {
  for (const saved of savedLessons()) {
    await flush(saved.grant).catch(() => null);
  }
}

/* ---------- HMAC-SHA256, from the platform's SHA-256 ---------- */

const encoder = new TextEncoder();

type Bytes = Uint8Array<ArrayBuffer>;

async function sha256(bytes: Bytes): Promise<Bytes> {
  return new Uint8Array(await Crypto.digest(Crypto.CryptoDigestAlgorithm.SHA256, bytes));
}

function join(a: Bytes, b: Bytes): Bytes {
  const out = new Uint8Array(new ArrayBuffer(a.length + b.length));
  out.set(a);
  out.set(b, a.length);

  return out;
}

/** HMAC-SHA256 (RFC 2104) as hex, what the server checks offline time with. */
export async function hmacSha256Hex(key: string, message: string): Promise<string> {
  let secret: Bytes = encoder.encode(key);
  if (secret.length > 64) secret = await sha256(secret);

  const block = new Uint8Array(new ArrayBuffer(64));
  block.set(secret);
  const inner = await sha256(join(block.map((b) => b ^ 0x36), encoder.encode(message)));
  const outer = await sha256(join(block.map((b) => b ^ 0x5c), inner));

  return Array.from(outer, (b) => b.toString(16).padStart(2, '0')).join('');
}
