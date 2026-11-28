/**
 * Open houses on the phone (B14.3a, ADR 0066): start sign-in, show the QR
 * code by the door, or hand the phone to visitors (kiosk mode).
 *
 * Kiosk sign-ins are saved on the phone first (open houses often have no
 * signal), each with its own id, and sent when the phone is online. The
 * server adds each one once, so sending again after a dropped connection
 * never doubles a contact.
 */
import type { paths } from '@reos/api-client';
import * as Crypto from 'expo-crypto';
import { createMMKV } from 'react-native-mmkv';

import { api } from '@/lib/api';

type Got<P extends keyof paths> = paths[P] extends { get: { responses: { 200: { content: { 'application/json': infer B } } } } } ? B : never;
type Body<P extends keyof paths> = paths[P] extends { post: { requestBody?: { content: { 'application/json': infer B } } } } ? B : never;

export type OpenHouse = Got<'/open-houses'>['data'][number];
export type OpenHouseVisit = Got<'/open-houses/{openHouse}/visits'>['data'][number];
export type KioskVisit = Body<'/open-houses/{openHouse}/visits'>['visits'][number];
export type Timeline = NonNullable<OpenHouseVisit['timeline']>;
/** What a visitor fills in; they only get this far by agreeing to be contacted. */
export type Visitor = { name: string; email: string | null; phone: string | null; has_agent: boolean; pre_approved: boolean; timeline: Timeline | null; consent: true };

export const TIMELINES: Timeline[] = ['now', '3_months', '6_months', 'later', 'just_looking'];

const store = createMMKV({ id: 'reos.open-houses' });
const key = (house: string) => `pending.${house}`;

const fail = (error: unknown): never => {
  const e = (error as { errors?: Record<string, string[]>; message?: string } | undefined) ?? {};
  throw new Error(Object.values(e.errors ?? {})[0]?.[0] ?? e.message ?? 'error');
};

/** Whether the form can be saved: a name, consent, and an email or a phone. */
export function canSave(v: { name: string; email: string; phone: string; consent: boolean }): boolean {
  return v.name.trim().length >= 2 && v.consent && (v.email.trim() !== '' || v.phone.trim() !== '');
}

/** The queue without the sign-ins the server has now (by id). */
export function withoutSent(queue: KioskVisit[], sent: string[]): KioskVisit[] {
  return queue.filter((v) => !sent.includes(v.client_id));
}

export function pending(house: string): KioskVisit[] {
  const raw = store.getString(key(house));

  if (raw === undefined) return [];

  try {
    return JSON.parse(raw) as KioskVisit[];
  } catch {
    return [];
  }
}

const keep = (house: string, queue: KioskVisit[]) => (queue.length === 0 ? store.remove(key(house)) : store.set(key(house), JSON.stringify(queue)));

/** Saves a sign-in on the phone at once. Returns how many are waiting. */
export function saveVisit(house: string, visitor: Visitor, now = new Date()): number {
  const clean = (s: string | null) => (s === null || s.trim() === '' ? null : s.trim());
  const queue: KioskVisit[] = [...pending(house), { ...visitor, name: visitor.name.trim(), email: clean(visitor.email), phone: clean(visitor.phone), client_id: Crypto.randomUUID(), signed_in_at: now.toISOString() }];
  keep(house, queue);

  return queue.length;
}

/**
 * Sends what's waiting. Returns how many are still waiting (0 when all
 * arrived); a failed send keeps them all for the next try.
 */
export async function sendPending(house: string): Promise<number> {
  const queue = pending(house);

  if (queue.length === 0) return 0;

  try {
    const { data } = await api().POST('/open-houses/{openHouse}/visits', { params: { path: { openHouse: house } }, body: { visits: queue } });

    if (!data) return queue.length;
  } catch {
    return queue.length;
  }

  // Anything saved while sending stays for the next round.
  const left = withoutSent(
    pending(house),
    queue.map((v) => v.client_id),
  );
  keep(house, left);

  return left.length;
}

export async function loadOpenHouses(): Promise<OpenHouse[]> {
  const { data } = await api().GET('/open-houses');

  return data ? data.data : [];
}

export async function startOpenHouse(address: string, city: string | null, startsAt: Date, endsAt: Date): Promise<OpenHouse> {
  const { data, error } = await api().POST('/open-houses', { body: { address, city, starts_at: startsAt.toISOString(), ends_at: endsAt.toISOString() } });

  return data ? data.data : fail(error);
}

export async function loadVisits(house: string): Promise<OpenHouseVisit[]> {
  const { data } = await api().GET('/open-houses/{openHouse}/visits', { params: { path: { openHouse: house } } });

  return data ? data.data : [];
}

/** Started on the phone at the door: from now, for three hours (the web sets other times). */
export function fromNow(now: Date): { starts: Date; ends: Date } {
  return { starts: now, ends: new Date(now.getTime() + 3 * 3600_000) };
}
