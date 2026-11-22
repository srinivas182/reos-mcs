/**
 * Messages on the phone (B6.1): the inbox, one conversation and the people
 * you may message, from `/messages`, live through Reverb while the app is
 * open. A 404 means Messages isn't rolled out to this person yet (flag
 * `messages`), and the entry stays hidden.
 */
import type { paths } from '@reos/api-client';
import { useFocusEffect } from 'expo-router';
import { useCallback, useEffect, useState } from 'react';

import { api } from '@/lib/api';
import { listen } from '@/lib/realtime';

type Json<P extends keyof paths> = paths[P] extends { get: { responses: { 200: { content: { 'application/json': infer B } } } } } ? B : never;

export type Inbox = Json<'/messages'>;
export type InboxItem = Inbox['conversations'][number];
export type ConversationView = Json<'/messages/{conversation}'>;
export type MessageItem = ConversationView['messages'][number];
export type Contact = Json<'/messages/contacts'>['contacts'][number];

/** The inbox and unread total; null while loading or when Messages isn't on for this person. */
export function useInbox(userId: number | null) {
  const [inbox, setInbox] = useState<Inbox | null>(null);

  const load = useCallback(() => {
    void api()
      .GET('/messages')
      .then(({ data }) => setInbox(data ?? null))
      .catch(() => undefined);
  }, []);

  useFocusEffect(load);

  useEffect(() => (userId === null ? undefined : listen(`users.${userId}`, ['message.posted'], load)), [userId, load]);

  return { inbox, reload: load };
}

export function useConversation(id: string) {
  const [conversation, setConversation] = useState<ConversationView | null>(null);
  const [failed, setFailed] = useState(false);

  const load = useCallback(() => {
    void api()
      .GET('/messages/{conversation}', { params: { path: { conversation: id } } })
      .then(({ data }) => {
        setFailed(!data);
        if (data) {
          setConversation(data);
          void api().POST('/messages/{conversation}/read', { params: { path: { conversation: id } } });
        }
      })
      .catch(() => setFailed(true));
  }, [id]);

  useFocusEffect(load);

  useEffect(() => listen(`conversations.${id}`, ['message.posted', 'message.changed', 'conversation.read'], load), [id, load]);

  return { conversation, failed, reload: load };
}

export async function sendMessage(conversation: string, body: string, replyTo: string | null): Promise<boolean> {
  const { data } = await api().POST('/messages/{conversation}', { params: { path: { conversation } }, body: { body, reply_to: replyTo } });

  return data !== undefined;
}

export async function editMessage(message: string, body: string): Promise<boolean> {
  const { data } = await api().PATCH('/messages/items/{message}', { params: { path: { message } }, body: { body } });

  return data !== undefined;
}

export async function deleteMessage(message: string): Promise<boolean> {
  const { data } = await api().DELETE('/messages/items/{message}', { params: { path: { message } } });

  return data !== undefined;
}

export async function startConversation(people: number[], body: string, title: string | null): Promise<string | null> {
  const { data } = await api().POST('/messages', { body: { people, body, title } });

  return data?.id ?? null;
}

export async function fetchContacts(): Promise<Contact[]> {
  const { data } = await api().GET('/messages/contacts');

  return data?.contacts ?? [];
}

/** Whether everyone else has read up to this message ("Read"). */
export function readByAll(conversation: ConversationView, message: MessageItem): boolean {
  return conversation.reads.length > 0 && conversation.reads.every((r) => r.read_at !== null && r.read_at >= message.created_at);
}

/** Still within the 5 minutes when the sender can edit or delete it. */
export function changeable(message: MessageItem): boolean {
  return message.changeable_until !== null && new Date(message.changeable_until) > new Date();
}
