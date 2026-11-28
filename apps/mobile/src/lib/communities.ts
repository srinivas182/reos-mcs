/**
 * Communities on the phone (B17.2): communities I'm in, a community's
 * sessions and posts, RSVP, and joining from an invite link or QR code.
 */
import type { paths } from '@reos/api-client';

import { api } from '@/lib/api';

type Got<P extends keyof paths> = paths[P] extends { get: { responses: { 200: { content: { 'application/json': infer B } } } } } ? B : never;

export type CommunityCard = Got<'/communities'>['data'][number];
export type CommunityView = Got<'/communities/{community}'>['data'];
export type CommunitySession = CommunityView['sessions'][number];
export type CommunityInvite = Got<'/communities/join/{code}'>['data'];

const fail = (error: unknown): never => {
  const e = (error as { errors?: Record<string, string[]>; message?: string } | undefined) ?? {};
  throw new Error(Object.values(e.errors ?? {})[0]?.[0] ?? e.message ?? 'error');
};

export async function loadCommunities(): Promise<CommunityCard[]> {
  const { data } = await api().GET('/communities');

  return data ? data.data : [];
}

export async function loadCommunity(community: string): Promise<CommunityView | null> {
  const { data } = await api().GET('/communities/{community}', { params: { path: { community } } });

  return data ? data.data : null;
}

export async function loadInvite(code: string): Promise<CommunityInvite | null> {
  const { data } = await api().GET('/communities/join/{code}', { params: { path: { code } } });

  return data ? data.data : null;
}

/** Joins; returns the community's id. */
export async function joinCommunity(code: string, via: 'link' | 'qr'): Promise<string> {
  const { data, error } = await api().POST('/communities/join/{code}', { params: { path: { code } }, body: { via } });

  return data ? data.data.id : fail(error);
}

/** Free: going. Paid: held, with the checkout link to open. */
export async function rsvp(session: string): Promise<{ status: string; checkout_url: string | null }> {
  const { data, error } = await api().POST('/community-sessions/{session}/rsvp', { params: { path: { session } } });

  return data ? data.data : fail(error);
}

export async function cancelRsvp(session: string): Promise<void> {
  const { error } = await api().DELETE('/community-sessions/{session}/rsvp', { params: { path: { session } } });

  if (error) {
    fail(error);
  }
}

/** Checked first: the reason comes back as the error. */
export async function postTo(community: string, body: string): Promise<void> {
  const { error } = await api().POST('/communities/{community}/posts', { params: { path: { community } }, body: { body } });

  if (error) {
    fail(error);
  }
}

/** The invite code from a scanned or tapped link (…/join/community/<code>?via=qr). */
export function inviteFrom(url: string): { code: string; via: 'link' | 'qr' } | null {
  const match = /\/join\/community\/([a-z0-9]{6,24})(\?.*)?$/.exec(url);

  return match ? { code: match[1] ?? '', via: (match[2] ?? '').includes('via=qr') ? 'qr' : 'link' } : null;
}
