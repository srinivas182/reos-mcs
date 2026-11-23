/**
 * Contacts, one person from all sources (B7.1, ADR 0027): the list, Sync
 * activity (screen 23) and phone contacts. The phone's contacts are read on
 * the phone with permission, sent in batches, and never written back.
 */
import { useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';

import type { Schemas } from '@reos/api-client';

import { api } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import type { PhoneRecord } from '@/lib/contacts';

export type Contact = Schemas['ContactResource'];

export type Activity = {
  id: number;
  kind: string;
  contact: { id: string; name: string };
  source: string;
  source_name: string;
  text: string;
  occurred_at: string;
};

export type Suggestion = { id: number; contact: { id: string; name: string }; other: { id: string; name: string } };

export const BATCH = 500;

export function useContacts() {
  const { status } = useAuth();
  const [contacts, setContacts] = useState<Contact[] | null>(null);

  const load = useCallback(async () => {
    if (status !== 'signedIn') return;
    const { data } = await api().GET('/contacts');
    setContacts(data?.data ?? []);
  }, [status]);

  useFocusEffect(
    useCallback(() => {
      void load();
    }, [load]),
  );

  const phoneOn = (contacts ?? []).some((c) => c.sources.some((s) => s.source === 'phone'));

  return { contacts, phoneOn, reload: load };
}

export function useSyncActivity() {
  const { status } = useAuth();
  const [activity, setActivity] = useState<Activity[] | null>(null);
  const [suggestions, setSuggestions] = useState<Suggestion[]>([]);

  const load = useCallback(async () => {
    if (status !== 'signedIn') return;
    const { data } = await api().GET('/contacts/activity');
    setActivity((data?.data as Activity[] | undefined) ?? []);
    setSuggestions((data?.suggestions as Suggestion[] | undefined) ?? []);
  }, [status]);

  useFocusEffect(
    useCallback(() => {
      void load();
    }, [load]),
  );

  return { activity, suggestions, reload: load };
}

/** Sends the phone's contacts in batches; returns how many were sent, or null when one failed. */
export async function sendPhoneContacts(records: PhoneRecord[]): Promise<number | null> {
  for (let at = 0; at < records.length; at += BATCH) {
    const { response } = await api().POST('/contacts/phone', { body: { contacts: records.slice(at, at + BATCH) } });

    if (!response.ok) return null;
  }

  return records.length;
}
