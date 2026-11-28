/**
 * Joining the provider network on the phone (B15.1, provider screen 2):
 * the categories, their licence step and profile fields come from the super
 * admin's settings on reos.com.
 */
import type { paths } from '@reos/api-client';

import { api } from '@/lib/api';

type Got<P extends keyof paths> = paths[P] extends { get: { responses: { 200: { content: { 'application/json': infer B } } } } } ? B : never;

export type ProviderCategory = Got<'/provider/categories'>['data'][number];
export type JoinField = ProviderCategory['fields'][number];
export type Joining = Got<'/provider/profile'>['data'];
export type Answer = Joining['fields'][string];

const fail = (error: unknown): never => {
  const e = (error as { errors?: Record<string, string[]>; message?: string } | undefined) ?? {};
  throw new Error(Object.values(e.errors ?? {})[0]?.[0] ?? e.message ?? 'error');
};

export async function loadCategories(state = 'TX'): Promise<ProviderCategory[]> {
  const { data } = await api().GET('/provider/categories', { params: { query: { state } } });

  return data ? data.data : [];
}

export async function loadJoining(): Promise<Joining | null> {
  const { data } = await api().GET('/provider/profile');

  return data ? data.data : null;
}

export async function chooseCategory(key: string): Promise<Joining> {
  const { data, error } = await api().PUT('/provider/profile/category', { body: { category: key } });

  return data ? data.data : fail(error);
}

/** Saves the answers, leaving out blanks; lists are split on commas when typed. */
export async function saveAnswers(answers: Record<string, Answer>): Promise<Joining> {
  const fields: Record<string, Answer> = {};

  for (const [key, value] of Object.entries(answers)) {
    const clean = Array.isArray(value) ? value.map((v) => v.trim()).filter((v) => v !== '') : typeof value === 'string' ? value.trim() : value;

    if (clean !== '' && !(Array.isArray(clean) && clean.length === 0)) fields[key] = clean;
  }

  const { data, error } = await api().PUT('/provider/profile/fields', { body: { fields } });

  return data ? data.data : fail(error);
}

export async function requestCategory(name: string, note: string): Promise<{ name: string; requests: number }> {
  const { data, error } = await api().POST('/provider/categories/requests', { body: { name: name.trim(), note: note.trim() === '' ? null : note.trim() } });

  return data ? data.data : fail(error);
}

/** The cheapest plan in the state, in cents, if one is priced. */
export function lowestPrice(category: ProviderCategory): number | null {
  const prices = [category.prices.basic, category.prices.pro, category.prices.premium].filter((p): p is number => p !== null);

  return prices.length === 0 ? null : Math.min(...prices);
}
