/**
 * Privacy switches and consents on the phone (A6.3, Me › Privacy): read
 * from `/me/consents`, changed with `PUT /me/consents/{purpose}` (recorded
 * as given "in the app"). The terms and privacy notice open on reos.com.
 */
import type { paths } from '@reos/api-client';
import { useFocusEffect } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { useCallback, useState } from 'react';

import { api, webBaseUrl } from '@/lib/api';

type Json<P extends keyof paths> = paths[P] extends { get: { responses: { 200: { content: { 'application/json': infer B } } } } } ? B : never;

export type ConsentSummary = Json<'/me/consents'>;
export type ConsentSwitch = ConsentSummary['switches'][number];
export type ConsentRecord = ConsentSummary['records'][number];

export function useConsents() {
  const [summary, setSummary] = useState<ConsentSummary | null>(null);
  const [failed, setFailed] = useState(false);

  useFocusEffect(
    useCallback(() => {
      void api()
        .GET('/me/consents')
        .then(({ data }) => {
          setFailed(!data);
          if (data) setSummary(data);
        })
        .catch(() => setFailed(true));
    }, []),
  );

  /** Turn a switch on or off, or withdraw a consent for one thing; false if it didn't save. */
  const change = useCallback(async (purpose: ConsentRecord['purpose'], granted: boolean, subject: string | null = null): Promise<boolean> => {
    const { data } = await api().PUT('/me/consents/{purpose}', { params: { path: { purpose } }, body: { granted, subject } });

    if (data) setSummary(data);

    return data !== undefined;
  }, []);

  return { summary, failed, change };
}

/** The terms or the privacy notice, on reos.com in an in-app browser. */
export function openLegal(document: 'terms' | 'privacy'): void {
  void WebBrowser.openBrowserAsync(`${webBaseUrl()}/${document}`);
}
