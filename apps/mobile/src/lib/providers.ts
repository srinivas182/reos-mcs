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

export type Onboarding = Got<'/provider/join'>['data'];
export type StepKey = Onboarding['steps'][number]['key'];
export type ServiceArea = Onboarding['areas'][number];
export type Plan = Onboarding['plans'][number];

/** The step screens, in order (provider screens 1–6). */
export const STEP_SCREENS: Record<StepKey, string> = {
  welcome: '/provider/join',
  category: '/provider/category',
  licence: '/provider/licence',
  areas: '/provider/areas',
  plan: '/provider/plan',
  rules: '/provider/rules',
};

export async function loadOnboarding(): Promise<Onboarding | null> {
  const { data } = await api().GET('/provider/join');

  return data ? data.data : null;
}

export async function welcomeDone(): Promise<Onboarding> {
  const { data, error } = await api().POST('/provider/join/welcome');

  return data ? data.data : fail(error);
}

export async function addLicence(kind: 'nmls' | 'trec_inspector' | 'tdi', number: string): Promise<void> {
  const { error } = await api().POST('/provider/licences', { body: { kind, number: number.trim() } });

  if (error) fail(error);
}

/** Business details and a photo ID (a file from the camera or library), for categories without a licence. */
export async function saveBusiness(name: string, address: string, photo: { uri: string; name: string; type: string } | null): Promise<Onboarding> {
  const body = new FormData();
  body.append('name', name.trim());
  body.append('address', address.trim());

  if (photo) body.append('id_photo', photo as unknown as Blob);

  const { data, error } = await api().POST('/provider/join/business', { body: { name, address }, bodySerializer: () => body });

  return data ? data.data : fail(error);
}

export async function addArea(area: { kind: 'city' | 'zip' | 'radius'; city?: string; postal_code?: string; radius_miles?: number }): Promise<Onboarding> {
  const { data, error } = await api().POST('/provider/join/areas', { body: { kind: area.kind, city: area.city ?? null, postal_code: area.postal_code ?? null, radius_miles: area.radius_miles ?? null } });

  return data ? data.data : fail(error);
}

export async function removeArea(id: number): Promise<Onboarding> {
  const { data, error } = await api().DELETE('/provider/join/areas/{area}', { params: { path: { area: id } } });

  return data ? data.data : fail(error);
}

/** Starts the plan's checkout; the phone opens it on reos.com. */
export async function startPlan(tier: Plan['tier']): Promise<string> {
  const { data, error } = await api().POST('/provider/join/plan', { body: { tier } });

  return data ? data.data.checkout_url : fail(error);
}

export async function acceptRules(version: string): Promise<Onboarding> {
  const { data, error } = await api().POST('/provider/join/rules', { body: { version, accept: true } });

  return data ? data.data : fail(error);
}

/** The first step still to do. */
export function nextStep(o: Onboarding): StepKey | null {
  return o.steps.find((s) => !s.done)?.key ?? null;
}

/** How an area reads: "Katy", "77494", or "15 miles around 77494". */
export function areaParts(a: ServiceArea): { kind: ServiceArea['kind']; value: string; miles: number | null } {
  return { kind: a.kind, value: a.kind === 'city' ? (a.city ?? '') : (a.postal_code ?? ''), miles: a.radius_miles };
}
