/**
 * The compliance engine on the phone (B13.1): a text or email to a contact
 * is checked and fixed automatically before it opens in Messages or Mail.
 */
import { api } from '@/lib/api';

/**
 * The message with flagged wording fixed, and the phrases that changed.
 * If the check can't be reached, the message is left as it is.
 */
export async function fixBeforeSending(channel: 'email' | 'text', text: string): Promise<{ text: string; changed: string[] }> {
  if (text.trim() === '') return { text, changed: [] };

  const { data: before } = await api().POST('/compliance/check', { body: { channel, text } });
  const changed = (before?.data.issues ?? []).filter((i) => i.severity === 'block' && i.phrase).map((i) => i.phrase ?? '');

  if (changed.length === 0) return { text, changed };

  const { data } = await api().POST('/compliance/fix', { body: { channel, text } });

  return data ? { text: data.data.text, changed } : { text, changed: [] };
}
