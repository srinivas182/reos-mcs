/**
 * My listings on the phone (B10.1, in Deals): MLS listings where the agent
 * is the listing or co-listing agent, and the member record found by their
 * license number to confirm.
 */
import type { paths } from '@reos/api-client';

import { api } from '@/lib/api';

type Got<P extends keyof paths> = paths[P] extends { get: { responses: { 200: { content: { 'application/json': infer B } } } } } ? B : never;

export type ListingsOverview = Got<'/listings'>['data'];
export type Listing = ListingsOverview['listings'][number];
export type Member = ListingsOverview['candidates'][number];
export type PostDraft = Got<'/listings/{listing}/post'>['data'];
export type Cma = Got<'/listings/{listing}/cma'>['data'];
export type PageSettings = Got<'/listings/page'>['data'];

const fail = (error: unknown): never => {
  const e = (error as { errors?: Record<string, string[]>; message?: string } | undefined) ?? {};
  throw new Error(Object.values(e.errors ?? {})[0]?.[0] ?? e.message ?? 'error');
};

export async function loadListings(): Promise<ListingsOverview | null> {
  const { data } = await api().GET('/listings');

  return data?.data ?? null;
}

const body = (member: Member) => ({ mls: member.mls, member_key: member.member_key });

/** "Yes, this is me". */
export async function confirmMember(member: Member): Promise<ListingsOverview> {
  const { data, error } = await api().POST('/listings/members', { body: body(member) });

  return data ? data.data : fail(error);
}

/** "Not me". */
export async function declineMember(member: Member): Promise<ListingsOverview> {
  const { data, error } = await api().POST('/listings/members/decline', { body: body(member) });

  return data ? data.data : fail(error);
}

export async function unlinkMember(member: Member): Promise<ListingsOverview> {
  const { data, error } = await api().POST('/listings/members/unlink', { body: body(member) });

  return data ? data.data : fail(error);
}

/** The headline figure: the price, or what it sold for. */
export function priceText(listing: Listing, language: string, sold: (price: string) => string): string {
  const dollars = (value: number) => new Intl.NumberFormat(language, { style: 'currency', currency: 'USD', maximumFractionDigits: 0 }).format(value);

  if (listing.status === 'closed' && listing.close_price !== null) return sold(dollars(listing.close_price));

  return listing.price === null ? '—' : dollars(listing.price);
}

/** The listing post: photos the MLS allows, the caption so far, the attribution. */
export async function loadPost(listing: string): Promise<PostDraft> {
  const { data, error } = await api().GET('/listings/{listing}/post', { params: { path: { listing } } });

  return data ? data.data : fail(error);
}

export async function savePost(listing: string, caption: string, photos: string[]): Promise<PostDraft> {
  const { data, error } = await api().PUT('/listings/{listing}/post', { params: { path: { listing } }, body: { caption, photos } });

  return data ? data.data : fail(error);
}

/** A CMA for one of my listings: radius in miles, sold within the last `months`. */
export const RADII = ['0.5', '1', '2', '5'] as const;
export const RANGES = ['3', '6', '12'] as const;
export type Radius = (typeof RADII)[number];
export type Range = (typeof RANGES)[number];

export async function loadCma(listing: string, radius: Radius, months: Range): Promise<Cma> {
  const { data, error } = await api().GET('/listings/{listing}/cma', { params: { path: { listing }, query: { radius, months } } });

  return data ? data.data : fail(error);
}

export async function loadPage(): Promise<PageSettings | null> {
  const { data } = await api().GET('/listings/page');

  return data?.data ?? null;
}

/** The card's details the agent chooses to show (B14.1). */
export type CardDetails = { phone: string | null; email: string | null; areas: string[] };

export async function savePage(published: boolean, bio: string | null, card?: CardDetails): Promise<PageSettings> {
  const { data, error } = await api().PUT('/listings/page', { body: { published, bio, ...card } });

  return data ? data.data : fail(error);
}

export const dollarsOf = (value: number | null, language: string): string =>
  value === null ? '—' : new Intl.NumberFormat(language, { style: 'currency', currency: 'USD', maximumFractionDigits: 0 }).format(value);

/** "Katy, Cinco Ranch" typed in one field: up to six areas, no blanks or repeats. */
export function areasFrom(text: string): string[] {
  return [...new Set(text.split(',').map((a) => a.trim()).filter((a) => a !== ''))].slice(0, 6);
}
