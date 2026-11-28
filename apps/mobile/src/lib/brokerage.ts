/**
 * Setting up a brokerage on the phone (A15.1, broker journey screens 2–4):
 * verify the broker license with TREC, bring in every sponsored agent, and
 * choose who pays for seats. The plan itself is bought on reos.com.
 */
import type { paths } from '@reos/api-client';

import { api } from '@/lib/api';

type Got<P extends keyof paths> = paths[P] extends { get: { responses: { 200: { content: { 'application/json': infer B } } } } } ? B : never;
type Posted<P extends keyof paths> = paths[P] extends { post: { responses: { 200: { content: { 'application/json': infer B } } } } } ? B : never;

export type BrokerLookup = Posted<'/brokerage/verify/lookup'>['data'];
export type BrokerConfirmed = Posted<'/brokerage/verify/confirm'>['data'];
export type BrokerRoleChoice = BrokerConfirmed['role'];
export type Setup = Got<'/organisation/setup'>['data'];
export type Roster = Got<'/organisation/roster'>['data'];
export type SeatsPlan = Got<'/organisation/seats'>['data'];
export type Payer = SeatsPlan['payer'];

export const ROLES: BrokerRoleChoice[] = ['designated_broker', 'office_admin', 'team_leader'];
export const PAYERS: Payer[] = ['organisation', 'member', 'split'];

const fail = (error: unknown): never => {
  const e = (error as { errors?: Record<string, string[]>; message?: string } | undefined) ?? {};
  throw new Error(Object.values(e.errors ?? {})[0]?.[0] ?? e.message ?? 'error');
};

/** The first brokerage that can still be set up, to pick by default. */
export function firstOpen(found: BrokerLookup): string | null {
  return found.brokerages.find((b) => !b.on_reos)?.number ?? null;
}

/** People who can be invited now: on REOS, or with an email or phone added. */
export function ready(roster: Roster): number {
  return roster.counts.on_reos + roster.counts.contact;
}

export async function lookupLicence(licence: string): Promise<BrokerLookup> {
  const { data, error } = await api().POST('/brokerage/verify/lookup', { body: { licence: licence.trim() } });

  return data ? data.data : fail(error);
}

export async function confirmBrokerage(licence: string, brokerage: string, role: BrokerRoleChoice): Promise<BrokerConfirmed> {
  const { data, error } = await api().POST('/brokerage/verify/confirm', { body: { licence: licence.trim(), brokerage, role } });

  return data ? data.data : fail(error);
}

export async function loadSetup(): Promise<Setup | null> {
  const { data } = await api().GET('/organisation/setup');

  return data ? data.data : null;
}

export async function loadRoster(): Promise<Roster | null> {
  const { data } = await api().GET('/organisation/roster');

  return data ? data.data : null;
}

export async function inviteAll(): Promise<Posted<'/organisation/roster/invite'>['data']> {
  const { data, error } = await api().POST('/organisation/roster/invite');

  return data ? data.data : fail(error);
}

export async function loadSeats(): Promise<SeatsPlan | null> {
  const { data } = await api().GET('/organisation/seats');

  return data ? data.data : null;
}

export async function chooseSeats(payer: Payer, pooled: boolean): Promise<SeatsPlan> {
  const { data, error } = await api().PUT('/organisation/seats', { body: { payer, credits_pooled: pooled } });

  return data ? data.data : fail(error);
}

export type Policies = Got<'/organisation/policies'>['data'];
export type BrokerageRule = Policies['rules'][number];
export type TrecConflict = NonNullable<BrokerageRule['conflict']>;

/** Brokerage › Policies (A15.2): the manual, the advertising policy and the brokerage's rules. */
export async function loadPolicies(): Promise<Policies | null> {
  const { data } = await api().GET('/organisation/policies');

  return data ? data.data : null;
}

/** The TREC rule the words contradict, if any, as the broker types. */
export async function checkRule(text: string): Promise<TrecConflict | null> {
  if (text.trim().length < 5) return null;
  const { data } = await api().POST('/organisation/policies/check', { body: { text } });

  return data ? data.data.conflict : null;
}

export async function addRule(topic: string, text: string): Promise<BrokerageRule> {
  const { data, error } = await api().POST('/organisation/policies/rules', { body: { topic: topic.trim(), text: text.trim() } });

  return data ? data.data : fail(error);
}
