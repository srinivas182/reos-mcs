/**
 * Farming market updates on the phone (B14.3c, ADR 0068): the areas the
 * agent farms, this month's update from their MLS figures, checked by the
 * compliance engine, and sending it to the contacts tagged for the area.
 */
import type { paths } from '@reos/api-client';

import { api } from '@/lib/api';

type Got<P extends keyof paths> = paths[P] extends { get: { responses: { 200: { content: { 'application/json': infer B } } } } } ? B : never;

export type FarmArea = Got<'/farm-areas'>['data'][number];
export type FarmDraft = Got<'/farm-areas/{area}/draft'>['data'];
export type FarmStats = FarmDraft['stats'];
export type ComplianceCheck = FarmDraft['check'];

export type AreaForm = { name: string; place: string; tag: string; address: string };

const fail = (error: unknown): never => {
  const e = (error as { errors?: Record<string, string[]>; message?: string } | undefined) ?? {};
  throw new Error(Object.values(e.errors ?? {})[0]?.[0] ?? e.message ?? 'error');
};

/** One field for the place: five digits are a ZIP code, anything else a city. */
export function areaBody(f: AreaForm): { name: string; city: string | null; postal_code: string | null; tag: string; mailing_address: string } {
  const place = f.place.trim();
  const zip = /^\d{5}$/.test(place);

  return { name: f.name.trim(), city: zip || place === '' ? null : place, postal_code: zip ? place : null, tag: f.tag.trim(), mailing_address: f.address.trim() };
}

export function canAdd(f: AreaForm): boolean {
  return f.name.trim().length >= 2 && f.place.trim() !== '' && f.tag.trim() !== '' && f.address.trim().length >= 10;
}

export type StatKey = 'active' | 'pending' | 'new' | 'sold' | 'medianPrice' | 'medianSold' | 'medianDays' | 'supply';

/** The figures to show, as label keys and values; sale prices only when the MLS allows them. */
export function statRows(stats: FarmStats, money: (n: number) => string): { key: StatKey; value: string }[] {
  const rows: { key: StatKey; value: string }[] = [
    { key: 'active', value: String(stats.active) },
    { key: 'pending', value: String(stats.pending) },
    { key: 'new', value: String(stats.new_listings) },
    { key: 'sold', value: String(stats.sold) },
  ];
  if (stats.median_price !== null) rows.push({ key: 'medianPrice', value: money(stats.median_price) });
  if (stats.median_sold_price !== null) rows.push({ key: 'medianSold', value: money(stats.median_sold_price) });
  if (stats.median_days !== null) rows.push({ key: 'medianDays', value: String(stats.median_days) });
  if (stats.months_supply !== null) rows.push({ key: 'supply', value: stats.months_supply.toFixed(1) });

  return rows;
}

export async function loadAreas(): Promise<FarmArea[]> {
  const { data } = await api().GET('/farm-areas');

  return data ? data.data : [];
}

export async function addArea(f: AreaForm): Promise<FarmArea> {
  const { data, error } = await api().POST('/farm-areas', { body: areaBody(f) });

  return data ? data.data : fail(error);
}

export async function removeArea(id: string): Promise<void> {
  const { error } = await api().DELETE('/farm-areas/{area}', { params: { path: { area: id } } });

  if (error) fail(error);
}

export async function loadDraft(id: string): Promise<FarmDraft> {
  const { data, error } = await api().GET('/farm-areas/{area}/draft', { params: { path: { area: id } } });

  return data ? data.data : fail(error);
}

export async function checkUpdate(subject: string, body: string): Promise<ComplianceCheck> {
  const { data, error } = await api().POST('/compliance/check', { body: { channel: 'newsletter', text: `${subject}\n\n${body}` } });

  return data ? data.data : fail(error);
}

export async function sendUpdate(id: string, subject: string, body: string): Promise<FarmDraft> {
  const { data, error } = await api().POST('/farm-areas/{area}/send', { params: { path: { area: id } }, body: { subject, body } });

  return data ? data.data : fail(error);
}
