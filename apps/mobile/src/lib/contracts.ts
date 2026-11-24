/**
 * Contract review on the phone (A12.2; agent screens 63–66): choose a PDF,
 * photos of pages, a deal's document or the sample; the review's steps;
 * the result; deadlines to the calendar; send to the broker; ask about it.
 */
import type { paths } from '@reos/api-client';

import { api } from '@/lib/api';
import type { PickedFile } from '@/lib/messages';

type Got<P extends keyof paths> = paths[P] extends { get: { responses: { 200: { content: { 'application/json': infer B } } } } } ? B : never;

export type ContractsOverview = Got<'/contracts'>['data'];
export type ContractReview = Got<'/contracts/{review}'>['data'];
export type Finding = ContractReview['findings'][number];
export const STEPS = ['read', 'terms', 'checks', 'compare'] as const;

const fail = (error: unknown): never => {
  const e = (error as { errors?: Record<string, string[]>; message?: string } | undefined) ?? {};
  throw new Error(Object.values(e.errors ?? {})[0]?.[0] ?? e.message ?? 'error');
};
const path = (review: string) => ({ params: { path: { review } } });

export async function loadContracts(): Promise<ContractsOverview | null> {
  const { data } = await api().GET('/contracts');

  return data ? data.data : null;
}

export async function loadReview(id: string): Promise<ContractReview | null> {
  const { data } = await api().GET('/contracts/{review}', path(id));

  return data ? data.data : null;
}

/** A PDF or photo from the phone, a deal's document, or the sample. */
export async function startReview(source: { file: PickedFile } | { dealFile: string } | { sample: true }): Promise<ContractReview> {
  const form = new FormData();

  if ('file' in source) {
    // React Native's FormData takes { uri, name, type } for a file.
    form.append('file', source.file as unknown as Blob);
  } else if ('dealFile' in source) {
    form.append('deal_file', source.dealFile);
  } else {
    form.append('sample', '1');
  }

  const { data, error } = await api().POST('/contracts', { body: {}, bodySerializer: () => form });

  return data ? data.data : fail(error);
}

export async function addDeadlines(id: string): Promise<ContractReview> {
  const { data, error } = await api().POST('/contracts/{review}/deadlines', path(id));

  return data ? data.data : fail(error);
}

export async function sendReviewToBroker(id: string): Promise<ContractReview> {
  const { data, error } = await api().POST('/contracts/{review}/broker', path(id));

  return data ? data.data : fail(error);
}

export async function askContract(id: string, question: string): Promise<ContractReview> {
  const { data, error } = await api().POST('/contracts/{review}/questions', { ...path(id), body: { question } });

  return data ? data.data : fail(error);
}
