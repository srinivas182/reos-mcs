/**
 * Deals on the phone (B9.1, ADR 0037): the pipeline (Agent App screen 9),
 * deal alerts (screen 10) and one deal. Deals from a transaction tool are
 * read-only here; their changes are made in the tool.
 */
import type { paths } from '@reos/api-client';
import { useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';

import { api } from '@/lib/api';

type Got<P extends keyof paths> = paths[P] extends { get: { responses: { 200: { content: { 'application/json': infer B } } } } } ? B : never;

export type Pipeline = Got<'/deals/pipeline'>['data'];
export type DealCard = Pipeline['deals'][number];
export type DealAlerts = Got<'/deals/alerts'>['data'];
export type Deal = Got<'/deals/{deal}'>['data'];

/** The tool's name, for "From Lone Wolf" and "Open in Lone Wolf". */
export const TOOL_NAMES: Record<string, string> = { 'lone-wolf': 'Lone Wolf', dotloop: 'dotloop', skyslope: 'SkySlope' };

export async function loadPipeline(): Promise<Pipeline | null> {
  const { data } = await api().GET('/deals/pipeline');

  return data?.data ?? null;
}

export async function loadAlerts(): Promise<DealAlerts | null> {
  const { data } = await api().GET('/deals/alerts');

  return data?.data ?? null;
}

export async function loadDeal(uuid: string): Promise<Deal | null> {
  const { data } = await api().GET('/deals/{deal}', { params: { path: { deal: uuid } } });

  return data?.data ?? null;
}

/** Loads on focus; `undefined` while loading, `null` if it couldn't. */
export function useLoaded<T>(load: () => Promise<T | null>, enabled = true): { value: T | null | undefined; reload: () => void } {
  const [value, setValue] = useState<T | null | undefined>(undefined);
  const [tick, setTick] = useState(0);
  useFocusEffect(
    useCallback(() => {
      if (!enabled) return undefined;
      let active = true;
      void load()
        .catch(() => null)
        .then((found) => active && setValue(found));

      return () => {
        active = false;
      };
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [enabled, tick]),
  );

  return { value, reload: () => setTick((n) => n + 1) };
}

/** "$12,750". */
export function money(value: number | string, language: string): string {
  return new Intl.NumberFormat(language, { style: 'currency', currency: 'USD', maximumFractionDigits: 0 }).format(Number(value));
}
