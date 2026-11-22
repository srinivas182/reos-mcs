/**
 * Earnings on the phone (instructor screen 20, mentor screen 28; B5.2b):
 * read from `/me/earnings`. Bank and tax details are managed on Stripe from
 * the web, never in the app.
 */
import type { paths } from '@reos/api-client';
import { useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';

import { api } from '@/lib/api';

type Json<P extends keyof paths> = paths[P] extends { get: { responses: { 200: { content: { 'application/json': infer B } } } } } ? B : never;

export type Earnings = Json<'/me/earnings'>;
export type EarningLine = Earnings['lines'][number];

/** A group of shares from the same product and role ("Texas Pre-licence Complete (31)"). */
export type EarningGroup = { key: string; product: string; role: EarningLine['role']; count: number; amount: number; lines: EarningLine[] };

export function useEarnings() {
  const [earnings, setEarnings] = useState<Earnings | null>(null);
  const [failed, setFailed] = useState(false);

  useFocusEffect(
    useCallback(() => {
      void api()
        .GET('/me/earnings')
        .then(({ data }) => {
          setFailed(!data);
          if (data) setEarnings(data);
        })
        .catch(() => setFailed(true));
    }, []),
  );

  return { earnings, failed };
}

/** This month's shares, grouped by product and role, biggest first. Refunded ones don't count. */
export function groupLines(lines: EarningLine[], month: string): EarningGroup[] {
  const groups = new Map<string, EarningGroup>();

  for (const line of lines) {
    if (!line.date?.startsWith(month) || line.status === 'cancelled' || line.status === 'reversed') continue;

    const key = `${line.product}:${line.role}`;
    const group = groups.get(key) ?? { key, product: line.product, role: line.role, count: 0, amount: 0, lines: [] };
    group.count += 1;
    group.amount += line.amount;
    group.lines.push(line);
    groups.set(key, group);
  }

  return [...groups.values()].sort((a, b) => b.amount - a.amount);
}

export function money(cents: number, locale: string): string {
  return new Intl.NumberFormat(locale, { style: 'currency', currency: 'USD', maximumFractionDigits: cents % 100 === 0 ? 0 : 2 }).format(cents / 100);
}
