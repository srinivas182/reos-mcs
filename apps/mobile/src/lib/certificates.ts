/**
 * CE certificates on the phone (B10.3b): a photo or PDF read by OCR, the
 * details checked, then saved as a completion.
 */
import type { paths } from '@reos/api-client';

import { api } from '@/lib/api';
import type { PickedFile } from '@/lib/messages';

type Posted<P extends keyof paths> = paths[P] extends { post: { responses: { 200: { content: { 'application/json': infer B } } } } } ? B : never;

export type CertificateRead = Posted<'/me/certificates/read'>['data'];
export type CertificateValues = { state: string; title: string; hours: number; completed_on: string; provider: string | null };

const fail = (error: unknown): never => {
  const e = (error as { errors?: Record<string, string[]>; message?: string } | undefined) ?? {};
  throw new Error(Object.values(e.errors ?? {})[0]?.[0] ?? e.message ?? 'error');
};

export async function readCertificate(file: PickedFile): Promise<CertificateRead> {
  const form = new FormData();
  // React Native's FormData takes { uri, name, type } for a file.
  form.append('file', file as unknown as Blob);
  const { data, error } = await api().POST('/me/certificates/read', { body: { file: '' }, bodySerializer: () => form });

  return data ? data.data : fail(error);
}

export async function saveCertificate(upload: string, values: CertificateValues): Promise<void> {
  const { error } = await api().POST('/me/certificates', { body: { upload, ...values } });

  if (error) fail(error);
}
