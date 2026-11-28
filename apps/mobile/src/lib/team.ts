/**
 * Teams on the phone (A17.3, team leader screens 28–30): lead routing and
 * the rotation for team leaders; leads to claim and split sheets for agents.
 */
import type { paths } from '@reos/api-client';

import { api } from '@/lib/api';

type Got<P extends keyof paths> = paths[P] extends { get: { responses: { 200: { content: { 'application/json': infer B } } } } } ? B : never;

export type RoutingBoard = Got<'/team/routing'>['data'];
export type TeamMine = Got<'/team/mine'>;
export type TeamLead = TeamMine['offered'][number];
export type SplitSheet = TeamMine['splits'][number];

const fail = (error: unknown): never => {
  const e = (error as { errors?: Record<string, string[]>; message?: string } | undefined) ?? {};
  throw new Error(Object.values(e.errors ?? {})[0]?.[0] ?? e.message ?? 'error');
};

export async function loadRouting(): Promise<RoutingBoard | null> {
  const { data } = await api().GET('/team/routing');

  return data ? data.data : null;
}

export async function setInRotation(agent: number, active: boolean, languages: ('en' | 'es')[]): Promise<RoutingBoard> {
  const { data, error } = await api().PUT('/team/routing/members/{agent}', { params: { path: { agent } }, body: { active, languages } });

  return data ? data.data : fail(error);
}

export async function loadMine(): Promise<TeamMine | null> {
  const { data } = await api().GET('/team/mine');

  return data ?? null;
}

/** The first to claim in time gets the lead. */
export async function claimLead(id: string): Promise<void> {
  const { error } = await api().POST('/team/leads/{offer}/claim', { params: { path: { offer: id } } });

  if (error) {
    fail(error);
  }
}

export async function signSplit(id: string): Promise<void> {
  const { error } = await api().POST('/team/splits/{sheet}/sign', { params: { path: { sheet: id } } });

  if (error) {
    fail(error);
  }
}

/** Minutes left to claim, never below zero. */
export function minutesLeft(claimBy: string | null, now: Date = new Date()): number {
  return claimBy === null ? 0 : Math.max(0, Math.ceil((new Date(claimBy).getTime() - now.getTime()) / 60000));
}
