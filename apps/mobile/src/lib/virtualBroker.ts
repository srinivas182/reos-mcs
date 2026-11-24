/**
 * The virtual broker on the phone (A12.1; agent screens 61 and 62): ask,
 * answers with sources and confidence, Send to my broker, and the broker's
 * answer labelled Confirmed or Corrected.
 */
import type { paths } from '@reos/api-client';
import type { TFunction } from 'i18next';

import { api } from '@/lib/api';
import type { Tone } from '@/ui/tones';

type Got<P extends keyof paths> = paths[P] extends { get: { responses: { 200: { content: { 'application/json': infer B } } } } } ? B : never;

export type VirtualBroker = Got<'/virtual-broker'>['data'];
export type BrokerQuestion = VirtualBroker['questions'][number];
export type PolicySource = BrokerQuestion['sources'][number];

export const STATUS_TONE: Record<BrokerQuestion['status'], Tone> = { answered: 'neutral', waiting: 'warning', confirmed: 'success', corrected: 'success' };
export const CONFIDENCE_TONE: Record<BrokerQuestion['confidence'], Tone> = { high: 'success', medium: 'info', low: 'warning' };

const fail = (error: unknown): never => {
  const e = (error as { errors?: Record<string, string[]>; message?: string } | undefined) ?? {};
  throw new Error(Object.values(e.errors ?? {})[0]?.[0] ?? e.message ?? 'error');
};

/** My brokerage's virtual broker, or null when I'm not an agent at a brokerage. */
export async function loadVirtualBroker(): Promise<VirtualBroker | null> {
  const { data } = await api().GET('/virtual-broker');

  return data ? data.data : null;
}

export async function loadQuestion(id: string): Promise<BrokerQuestion | null> {
  const { data } = await api().GET('/virtual-broker/questions/{question}', { params: { path: { question: id } } });

  return data ? data.data : null;
}

export async function askVirtualBroker(question: string): Promise<BrokerQuestion> {
  const { data, error } = await api().POST('/virtual-broker/questions', { body: { question } });

  return data ? data.data : fail(error);
}

export async function sendToBroker(id: string): Promise<BrokerQuestion> {
  const { data, error } = await api().POST('/virtual-broker/questions/{question}/escalate', { params: { path: { question: id } } });

  return data ? data.data : fail(error);
}

/** "Policy manual, page 12", "Brokerage FAQ" or the rule's citation. */
export function sourceLabel(source: PolicySource, t: TFunction): string {
  if (source.kind === 'rule') {
    return source.citation ?? source.title;
  }
  if (source.kind === 'faq') {
    return t('virtualBroker.source.faq');
  }

  return source.page !== null ? t('virtualBroker.source.page', { page: source.page }) : t('virtualBroker.source.manual');
}
