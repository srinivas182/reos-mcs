/**
 * The agent's AI assistant on the phone (A12.3; agent screens 59 and 60):
 * Ask home, chats, the permission switch, and where an answer's links go.
 */
import type { paths } from '@reos/api-client';

import { api } from '@/lib/api';

type Got<P extends keyof paths> = paths[P] extends { get: { responses: { 200: { content: { 'application/json': infer B } } } } } ? B : never;

export type AssistantHome = Got<'/assistant'>['data'];
export type AssistantChat = Got<'/assistant/chats/{chat}'>['data'];
export type AssistantMessage = AssistantChat['messages'][number];
export type Starter = AssistantHome['starters'][number];

const fail = (error: unknown): never => {
  const e = (error as { errors?: Record<string, string[]>; message?: string } | undefined) ?? {};
  throw new Error(Object.values(e.errors ?? {})[0]?.[0] ?? e.message ?? 'error');
};

export async function loadAssistant(): Promise<AssistantHome | null> {
  const { data } = await api().GET('/assistant');

  return data ? data.data : null;
}

export async function loadChat(id: string): Promise<AssistantChat | null> {
  const { data } = await api().GET('/assistant/chats/{chat}', { params: { path: { chat: id } } });

  return data ? data.data : null;
}

export async function askAssistant(question: string, chat: string | null): Promise<AssistantChat> {
  const { data, error } = await api().POST('/assistant/messages', { body: { question, chat } });

  return data ? data.data : fail(error);
}

export async function allowAssistant(allowed: boolean): Promise<boolean> {
  const { data, error } = await api().PUT('/assistant/permission', { body: { allowed } });

  return data ? data.data.permission : fail(error);
}

/** Where an item's link opens on the phone: a deal or a contact, or nowhere. */
export function itemRoute(link: string | null): { pathname: '/deals/[uuid]'; params: { uuid: string } } | { pathname: '/contacts/[id]'; params: { id: string } } | null {
  const deal = link?.match(/^\/deals\?deal=([0-9a-f-]{36})$/);
  if (deal?.[1]) return { pathname: '/deals/[uuid]', params: { uuid: deal[1] } };
  const contact = link?.match(/^\/contacts\/([0-9a-f-]{36})$/);
  if (contact?.[1]) return { pathname: '/contacts/[id]', params: { id: contact[1] } };

  return null;
}
