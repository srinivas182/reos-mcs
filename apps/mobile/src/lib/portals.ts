/**
 * Sharing a deal with a client from the phone (B14.2, ADR 0065): a
 * private link each, shared from the phone's share sheet or emailed.
 */
import type { paths } from '@reos/api-client';

import { api } from '@/lib/api';

type Got<P extends keyof paths> = paths[P] extends { get: { responses: { 200: { content: { 'application/json': infer B } } } } } ? B : never;

export type PortalLink = Got<'/deals/{deal}/portals'>['data'][number];

const fail = (error: unknown): never => {
  const e = (error as { errors?: Record<string, string[]>; message?: string } | undefined) ?? {};
  throw new Error(Object.values(e.errors ?? {})[0]?.[0] ?? e.message ?? 'error');
};

export async function loadPortals(deal: string): Promise<PortalLink[]> {
  const { data } = await api().GET('/deals/{deal}/portals', { params: { path: { deal } } });

  return data ? data.data : [];
}

export async function createPortal(deal: string, name: string, email: string | null, language: 'en' | 'es'): Promise<PortalLink> {
  const { data, error } = await api().POST('/deals/{deal}/portals', { params: { path: { deal } }, body: { name, email, language } });

  return data ? data.data : fail(error);
}

export async function emailPortal(portal: string): Promise<PortalLink> {
  const { data, error } = await api().POST('/portals/{portal}/send', { params: { path: { portal } } });

  return data ? data.data : fail(error);
}

export async function revokePortal(portal: string): Promise<void> {
  const { error } = await api().DELETE('/portals/{portal}', { params: { path: { portal } } });

  if (error) fail(error);
}

/** The deal's parties with an email who don't have a link yet. */
export function unshared<P extends { name: string; email: string | null }>(parties: P[], links: Pick<PortalLink, 'email'>[]): P[] {
  const shared = new Set(links.map((l) => l.email?.toLowerCase()));

  return parties.filter((p) => p.email !== null && !shared.has(p.email.toLowerCase()));
}
