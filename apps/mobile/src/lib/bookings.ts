/**
 * Booking 1-on-1 sessions on the phone (A10.2a, candidate screen 18;
 * instructor i14 and i16).
 */
import type { paths } from '@reos/api-client';

import { api } from '@/lib/api';

type Got<P extends keyof paths> = paths[P] extends { get: { responses: { 200: { content: { 'application/json': infer B } } } } } ? B : never;

export type InstructorOffer = Got<'/bookings/instructors'>['data'][number];
export type InstructorSlots = Got<'/bookings/instructors/{user}'>;
export type Booking = Got<'/bookings'>['data'][number];
export type SessionOffer = NonNullable<Got<'/bookings/offer'>['data']>;

const fail = (error: unknown): never => {
  const e = (error as { errors?: Record<string, string[]>; message?: string } | undefined) ?? {};
  throw new Error(Object.values(e.errors ?? {})[0]?.[0] ?? e.message ?? 'error');
};

export async function loadInstructors(): Promise<InstructorOffer[]> {
  const { data } = await api().GET('/bookings/instructors');

  return data?.data ?? [];
}

export async function loadInstructor(user: number): Promise<InstructorSlots | null> {
  const { data } = await api().GET('/bookings/instructors/{user}', { params: { path: { user } } });

  return data ?? null;
}

export async function loadBookings(): Promise<Booking[]> {
  const { data } = await api().GET('/bookings');

  return data?.data ?? [];
}

export async function book(body: { instructor: number; starts_at: string; minutes: number; topic: string }): Promise<{ booking: Booking; checkoutUrl: string | null }> {
  const { data, error } = await api().POST('/bookings', { body });

  return data ? { booking: data.data, checkoutUrl: data.checkout_url } : fail(error);
}

export async function cancelBooking(uuid: string): Promise<Booking> {
  const { data, error } = await api().POST('/bookings/{booking}/cancel', { params: { path: { booking: uuid } } });

  return data ? data.data : fail(error);
}

export async function rateBooking(uuid: string, stars: number): Promise<Booking> {
  const { data, error } = await api().POST('/bookings/{booking}/rating', { params: { path: { booking: uuid } }, body: { stars } });

  return data ? data.data : fail(error);
}

export async function suggestedTasks(uuid: string): Promise<string[]> {
  const { data } = await api().GET('/bookings/{booking}/tasks', { params: { path: { booking: uuid } } });

  return data?.data ?? [];
}

export async function sendTasks(uuid: string, tasks: { title: string; due_date: string | null }[]): Promise<number> {
  const { data, error } = await api().POST('/bookings/{booking}/tasks', { params: { path: { booking: uuid } }, body: { tasks } });

  return data ? data.data.sent : fail(error);
}

export const dollars = (cents: number, locale: string): string =>
  new Intl.NumberFormat(locale, { style: 'currency', currency: 'USD', minimumFractionDigits: cents % 100 === 0 ? 0 : 2 }).format(cents / 100);

/** "Tue, Nov 24, 1:00 PM" on this phone. */
export const slotText = (iso: string, locale: string): string =>
  new Date(iso).toLocaleString(locale, { weekday: 'short', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });

export async function loadOffer(): Promise<SessionOffer | null> {
  const { data } = await api().GET('/bookings/offer');

  return data?.data ?? null;
}

export async function saveOffer(body: SessionOffer): Promise<SessionOffer> {
  const { data, error } = await api().PUT('/bookings/offer', { body });

  return data?.data ? data.data : fail(error);
}
