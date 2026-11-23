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

/* The CRM hub (B8.3, ADR 0034): the list by tab or search, the AI next step, tasks and touches. */

export type ContactRow = Schemas['ContactRowResource'];
export type Insight = {
  summary: string;
  next_step: string;
  why: string;
  channel: 'call' | 'text' | 'email' | 'note';
  draft: string | null;
  engine: 'ai' | 'simulator';
  generated_at: string;
};
export type Segment = 'all' | 'new' | 'active' | 'past' | 'sphere';
export const SEGMENTS: Segment[] = ['all', 'new', 'active', 'past', 'sphere'];

export function useContactList(segment: Segment, search: string) {
  const { status } = useAuth();
  const [rows, setRows] = useState<ContactRow[] | null>(null);
  const [counts, setCounts] = useState<Record<string, number>>({});

  const load = useCallback(async () => {
    if (status !== 'signedIn') return;
    const q = search.trim();
    const { data } = await api().GET('/contacts', { params: { query: { segment, ...(q === '' ? {} : { q }) } } });
    setRows(data?.data ?? []);
    setCounts(data?.counts ?? {});
  }, [status, segment, search]);

  useFocusEffect(
    useCallback(() => {
      void load();
    }, [load]),
  );

  return { rows, counts, reload: load };
}

/** Today's "do this now" (Agent App Journey screen 6), for agents only. */
export function useTodayInsight(enabled: boolean) {
  const { status } = useAuth();
  const [today, setToday] = useState<{ contact: Contact; insight: Insight } | null>(null);

  const load = useCallback(async () => {
    if (status !== 'signedIn' || !enabled) return;
    const { data } = await api().GET('/contacts/today');
    setToday((data?.data as { contact: Contact; insight: Insight } | null | undefined) ?? null);
  }, [status, enabled]);

  useFocusEffect(
    useCallback(() => {
      void load();
    }, [load]),
  );

  return { today, reload: load };
}

export async function askInsight(contact: string): Promise<Insight | null> {
  const { data } = await api().POST('/contacts/{contact}/insight', { params: { path: { contact } } });

  return (data?.data as Insight | undefined) ?? null;
}

/** Logs a call or text made from the agent's own phone; it reaches every CRM that knows them. */
export async function logTouch(contact: string, channel: 'call' | 'text', body: string | null = null): Promise<boolean> {
  const { response } = await api().POST('/contacts/{contact}/touches', { params: { path: { contact } }, body: { channel, body } });

  return response.ok;
}

/** `tel:` or `sms:` with the message, opened in the phone's own app. */
export function reachUri(channel: 'call' | 'text', phone: string, body = ''): string {
  if (channel === 'call') return `tel:${phone}`;

  return body.trim() === '' ? `sms:${phone}` : `sms:${phone}?&body=${encodeURIComponent(body)}`;
}

/* Using two CRMs (screen 24, B8.3b): where new contacts go. */

export type CrmOption = { connection: number; integration: string; name: string; account: string | null; takes_new_people: boolean; is_default: boolean };

export function useCrms() {
  const { status } = useAuth();
  const [crms, setCrms] = useState<CrmOption[] | null>(null);

  const load = useCallback(async () => {
    if (status !== 'signedIn') return;
    const { data } = await api().GET('/contacts/crms');
    setCrms(data?.data ?? []);
  }, [status]);

  useFocusEffect(
    useCallback(() => {
      void load();
    }, [load]),
  );

  const choose = async (connection: number | null) => {
    const { data, response } = await api().PUT('/contacts/crms/default', { body: { connection } });
    if (response.ok) setCrms(data?.data ?? []);

    return response.ok;
  };

  return { crms, choose };
}

export type NewContact = { first_name: string; last_name: string; email: string; phone: string };

/** Saved in REOS and in the default CRM; returns the contact's id and where it went, or null. */
export async function addContact(person: NewContact): Promise<{ id: string; sentTo: string | null } | null> {
  const blank = (v: string) => (v.trim() === '' ? null : v.trim());
  const { data, response } = await api().POST('/contacts', {
    body: { first_name: blank(person.first_name), last_name: blank(person.last_name), email: blank(person.email), phone: blank(person.phone) },
  });
  const created = data as { data?: { id?: string }; sent_to?: string | null } | undefined;

  return response.ok && created?.data?.id ? { id: created.data.id, sentTo: created.sent_to ?? null } : null;
}
