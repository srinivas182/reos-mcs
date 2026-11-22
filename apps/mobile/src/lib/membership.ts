/**
 * Membership on the phone (B5.1b; candidate screen 6, decision 4). The app
 * never shows a price or sells: it says what a plan includes, offers "Email
 * me the link" (or the reos.com link when REOS switches it on, iPhone only;
 * Android shows the words without a link), takes a sponsor's code, and
 * unlocks from `/me/entitlements` the moment a purchase on the web lands:
 * asked again whenever the app comes back to the front.
 */
import type { paths } from '@reos/api-client';
import { useFocusEffect } from 'expo-router';
import { useCallback, useEffect, useState } from 'react';
import { AppState } from 'react-native';

import { api } from '@/lib/api';
import { useAuth } from '@/lib/auth';

type Json<P extends keyof paths, M extends 'get' | 'post'> = paths[P] extends Record<M, { responses: { 200: { content: { 'application/json': infer B } } } }> ? B : never;

export type MembershipSummary = Json<'/me/membership', 'get'>;
export type RedeemedCode = Json<'/me/codes', 'post'>;

export function useMembership() {
  const { status } = useAuth();
  const [membership, setMembership] = useState<MembershipSummary | null>(null);

  const load = useCallback(async () => {
    if (status !== 'signedIn') return;

    const { data } = await api().GET('/me/membership');
    if (data) setMembership(data);
  }, [status]);

  useFocusEffect(
    useCallback(() => {
      void load();
    }, [load]),
  );

  // Bought on the web while the app was in the background: unlock on return.
  useEffect(() => {
    const subscription = AppState.addEventListener('change', (state) => {
      if (state === 'active') void load();
    });

    return () => subscription.remove();
  }, [load]);

  return { membership, reload: load };
}

export async function emailMeTheLink(): Promise<boolean> {
  const { data } = await api().POST('/me/membership/email-link');

  return data?.sent === true;
}

export async function redeemCode(code: string): Promise<RedeemedCode | { error: string }> {
  const { data, error, response } = await api().POST('/me/codes', { body: { code } });

  if (response.ok && data) return data;

  const errors = (error as { errors?: Record<string, string[]>; message?: string } | undefined) ?? {};

  return { error: errors.errors?.code?.[0] ?? errors.message ?? 'Error' };
}
