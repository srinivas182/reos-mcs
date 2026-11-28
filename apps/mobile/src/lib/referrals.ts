/**
 * Referrals and invites on the phone (B14.3b, ADR 0067): refer a client
 * to an agent in another area (between brokers; REOS takes no fee),
 * answer referrals sent to you, and share your invite link.
 */
import type { paths } from '@reos/api-client';

import { api } from '@/lib/api';

type Got<P extends keyof paths> = paths[P] extends { get: { responses: { 200: { content: { 'application/json': infer B } } } } } ? B : never;
type Body<P extends keyof paths> = paths[P] extends { post: { requestBody?: { content: { 'application/json': infer B } } } } ? B : never;

export type Referral = Got<'/referrals'>['data']['sent'][number];
export type InviteSummary = Got<'/me/invite'>['data'];
export type ReferralBody = Body<'/referrals'>;

export type ReferralForm = {
  toName: string;
  toEmail: string;
  toBrokerage: string;
  clientName: string;
  clientEmail: string;
  clientPhone: string;
  kind: 'buyer' | 'seller';
  area: string;
  note: string;
  fee: string;
};

export const blankReferral: ReferralForm = { toName: '', toEmail: '', toBrokerage: '', clientName: '', clientEmail: '', clientPhone: '', kind: 'buyer', area: '', note: '', fee: '25' };

const fail = (error: unknown): never => {
  const e = (error as { errors?: Record<string, string[]>; message?: string } | undefined) ?? {};
  throw new Error(Object.values(e.errors ?? {})[0]?.[0] ?? e.message ?? 'error');
};

/** Whether the form can be sent: the agent, the client, a way to reach them, and their agreement. */
export function canRefer(f: ReferralForm, agreed: boolean): boolean {
  return agreed && f.toName.trim().length >= 2 && /.+@.+\..+/.test(f.toEmail.trim()) && f.clientName.trim().length >= 2 && (f.clientEmail.trim() !== '' || f.clientPhone.trim() !== '');
}

/** The request body: blanks as null, the fee as a whole number. */
export function referralBody(f: ReferralForm): ReferralBody {
  const text = (s: string) => (s.trim() === '' ? null : s.trim());
  const fee = Number.parseInt(f.fee, 10);

  return {
    to_name: f.toName.trim(),
    to_email: f.toEmail.trim(),
    to_brokerage: text(f.toBrokerage),
    client_name: f.clientName.trim(),
    client_email: text(f.clientEmail),
    client_phone: text(f.clientPhone),
    kind: f.kind,
    area: text(f.area),
    note: text(f.note),
    fee_percent: Number.isNaN(fee) ? null : fee,
    client_agreed: true,
  };
}

export async function loadReferrals(): Promise<{ sent: Referral[]; received: Referral[] }> {
  const { data } = await api().GET('/referrals');

  return data ? data.data : { sent: [], received: [] };
}

export async function sendReferral(f: ReferralForm): Promise<Referral> {
  const { data, error } = await api().POST('/referrals', { body: referralBody(f) });

  return data ? data.data : fail(error);
}

export async function answerReferral(id: string, answer: 'accept' | 'decline' | 'close'): Promise<Referral> {
  const path = { params: { path: { referral: id } } };
  const { data, error } = answer === 'accept' ? await api().POST('/referrals/{referral}/accept', path) : answer === 'decline' ? await api().POST('/referrals/{referral}/decline', path) : await api().POST('/referrals/{referral}/close', path);

  return data ? data.data : fail(error);
}

export async function loadInvite(): Promise<InviteSummary | null> {
  const { data } = await api().GET('/me/invite');

  return data ? data.data : null;
}

export async function claimInvite(code: string): Promise<InviteSummary> {
  const { data, error } = await api().POST('/me/invite/claim', { body: { code: code.trim().toUpperCase() } });

  return data ? data.data : fail(error);
}
