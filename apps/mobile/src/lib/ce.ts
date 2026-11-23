/**
 * The CE wallet on the phone (A11.2; agent screens 103, 105, 106, and Me →
 * CE wallet): hours against each licence's renewal, who reported each
 * completion, TREC's own view and the renewal checklist.
 */
import type { paths } from '@reos/api-client';
import { useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';

import { api } from '@/lib/api';

export type Wallet = paths['/me/ce']['get']['responses'][200]['content']['application/json']['data'][number];
export type CompletionStatus = Wallet['completions'][number]['status'];

/** Says renewed with the regulator (or takes it back); the new wallet, or null. */
export async function setRenewed(licence: number, renewed: boolean): Promise<Wallet | null> {
  const { data } = await api().PUT('/me/licences/{licence}/renewal', { params: { path: { licence } }, body: { renewed } });

  return data?.data ?? null;
}

export function useWallets() {
  const [wallets, setWallets] = useState<Wallet[] | null>(null);
  const reload = useCallback(async () => {
    const { data } = await api().GET('/me/ce');
    setWallets(data?.data ?? []);
  }, []);

  useFocusEffect(
    useCallback(() => {
      void reload().catch(() => setWallets([]));
    }, [reload]),
  );

  return { wallets, setWallets, reload };
}
