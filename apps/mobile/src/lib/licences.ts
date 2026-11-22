import type { Schemas } from '@reos/api-client';
import type { TFunction } from 'i18next';
import { useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';

import { api } from '@/lib/api';
import { useAuth } from '@/lib/auth';

/** A state licence (A5.1; agent journey screen 148). */
export type Licence = Schemas['LicenceResource'];
export type BrokerRole = NonNullable<Licence['broker_role']>;
export type LicenceCheck = 'verified' | 'name_mismatch' | 'not_found' | 'no_lookup';
export type CheckResult = { check: LicenceCheck; record: { name: string; type: Licence['type']; status: string; expires_on: string | null } | null; source: string | null };
export type StateOption = { code: string; name: string; live: boolean };

export const BROKER_ROLES: BrokerRole[] = ['sponsoring_broker', 'designated_broker', 'delegated_supervisor'];

/** My licences, reloaded whenever the screen comes back into view. */
export function useLicences() {
  const { status } = useAuth();
  const [licences, setLicences] = useState<Licence[] | null>(null);

  const load = useCallback(async () => {
    if (status !== 'signedIn') return;

    const { data } = await api().GET('/me/licences');
    setLicences(data?.data ?? []);
  }, [status]);

  useFocusEffect(
    useCallback(() => {
      void load();
    }, [load]),
  );

  return { licences, reload: load };
}

/** Every state, live ones first (the Add a licence picker). */
export async function loadStates(): Promise<StateOption[]> {
  const { data } = await api().GET('/states');

  return (data?.data ?? []).map((s) => ({ code: s.code, name: s.name, live: s.live })).sort((a, b) => Number(b.live) - Number(a.live) || a.name.localeCompare(b.name));
}

/** What the regulator holds for this number, without saving it. */
export async function checkLicence(state: string, number: string): Promise<CheckResult | { error: string }> {
  const { data, error, response } = await api().POST('/me/licences/check', { body: { state, number } });

  if (response.ok && data) return data;

  return { error: firstMessage(error) };
}

/** Saves the licence (checked again on the server). */
export async function addLicence(state: string, number: string, review: boolean): Promise<{ licence: Licence; check: LicenceCheck } | { error: string }> {
  const { data, error, response } = await api().POST('/me/licences', { body: { state, number, review } });

  if (response.ok && data) return { licence: data.data, check: data.check };

  return { error: firstMessage(error) };
}

export async function setBrokerRole(id: number, role: BrokerRole | null): Promise<boolean> {
  const { response } = await api().PATCH('/me/licences/{licence}', { params: { path: { licence: id } }, body: { broker_role: role } });

  return response.ok;
}

export async function removeLicence(id: number): Promise<boolean> {
  const { response } = await api().DELETE('/me/licences/{licence}', { params: { path: { licence: id } } });

  return response.ok;
}

export async function restoreLicence(id: number): Promise<boolean> {
  const { response } = await api().POST('/me/licences/{licence}/restore', { params: { path: { licence: id } } });

  return response.ok;
}

/** The state switcher (Me): content, plans and the CE card follow it. */
export async function switchState(code: string): Promise<boolean> {
  const { response } = await api().PUT('/me/states/current', { body: { state: code } });

  return response.ok;
}

/** "#0798765 · active · verified with TREC today" style line. */
export function licenceLine(licence: Licence, t: TFunction, language: string): string {
  const verified =
    licence.verified_at === null
      ? null
      : t(licence.verified_by === 'reviewer' ? 'mobile.licences.approvedOn' : 'mobile.licences.verifiedOn', {
          date: new Intl.DateTimeFormat(language, { month: 'short', day: 'numeric' }).format(new Date(licence.verified_at)),
        });

  return [`#${licence.number}`, t(`licences.statuses.${licence.status}`).toLowerCase(), verified].filter(Boolean).join(' · ');
}

/** "active", "expired"… in the language on screen. */
export function statusWord(status: string, t: TFunction): string {
  const known: Licence['status'][] = ['active', 'inactive', 'expired', 'pending_review', 'rejected'];

  return known.includes(status as Licence['status']) ? t(`licences.statuses.${status as Licence['status']}`).toLowerCase() : status;
}

export function formatDay(day: string | null, language: string): string | null {
  return day === null ? null : new Intl.DateTimeFormat(language, { month: 'short', day: 'numeric', year: 'numeric', timeZone: 'UTC' }).format(new Date(`${day}T00:00:00Z`));
}

function firstMessage(error: unknown): string {
  if (error && typeof error === 'object') {
    const errors = (error as { errors?: Record<string, string[]> }).errors;
    const first = errors ? Object.values(errors)[0]?.[0] : undefined;

    if (first) return first;

    const message = (error as { message?: string }).message;
    if (message) return message;
  }

  return '';
}
