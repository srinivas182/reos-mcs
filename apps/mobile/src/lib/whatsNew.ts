/**
 * "What's new" on Today (A6.2): the latest release note for the person's
 * role, from `/me/whats-new`, shown once until dismissed (also hides it on
 * the web).
 */
import type { paths } from '@reos/api-client';
import { useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';

import { api } from '@/lib/api';

type Json<P extends keyof paths> = paths[P] extends { get: { responses: { 200: { content: { 'application/json': infer B } } } } } ? B : never;

export type WhatsNew = NonNullable<Json<'/me/whats-new'>['release']>;

export function useWhatsNew() {
  const [item, setItem] = useState<WhatsNew | null>(null);

  useFocusEffect(
    useCallback(() => {
      void api()
        .GET('/me/whats-new')
        .then(({ data }) => setItem(data?.release ?? null))
        .catch(() => setItem(null));
    }, []),
  );

  const dismiss = useCallback(() => {
    if (item === null) return;
    const id = item.id;
    // Hide at once; the server remembers it for the web too.
    setItem(null);
    void api()
      .POST('/me/whats-new/{release}/dismiss', { params: { path: { release: id } } })
      .catch(() => undefined);
  }, [item]);

  return { item, dismiss };
}
