/**
 * The notification centre on the phone (B6.2): the inbox behind the bell on
 * Today, and Me › Notifications (each kind by channel, quiet hours in my
 * time zone, digests), push on this phone (B6.2b, `push.ts`) and texts
 * (B6.2c: a verified US number, then the switch that records consent).
 */
import type { paths } from '@reos/api-client';
import { useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';

import { api } from '@/lib/api';

type Json<P extends keyof paths> = paths[P] extends { get: { responses: { 200: { content: { 'application/json': infer B } } } } } ? B : never;

export type Inbox = Json<'/notifications'>;
export type InboxItem = Inbox['items'][number];
export type NotificationSettings = Json<'/me/notification-settings'>;
export type KindSetting = NotificationSettings['kinds'][number];

export function useNotificationInbox() {
  const [inbox, setInbox] = useState<Inbox | null>(null);

  const load = useCallback(() => {
    void api()
      .GET('/notifications')
      .then(({ data }) => setInbox(data ?? null))
      .catch(() => undefined);
  }, []);

  useFocusEffect(load);

  const read = useCallback(async (id?: string) => {
    if (id === undefined) await api().POST('/notifications/read');
    else await api().POST('/notifications/{notification}/read', { params: { path: { notification: id } } });
    load();
  }, [load]);

  return { inbox, read, reload: load };
}

export function useNotificationSettings() {
  const [settings, setSettings] = useState<NotificationSettings | null>(null);
  const [failed, setFailed] = useState(false);

  useFocusEffect(
    useCallback(() => {
      void api()
        .GET('/me/notification-settings')
        .then(({ data }) => {
          setFailed(!data);
          if (data) setSettings(data);
        })
        .catch(() => setFailed(true));
    }, []),
  );

  type Changes = NonNullable<paths['/me/notification-settings']['put']['requestBody']>['content']['application/json'];

  const save = useCallback(async (changes: Changes): Promise<boolean> => {
    const { data } = await api().PUT('/me/notification-settings', { body: changes });
    setFailed(!data);
    if (data) setSettings(data);

    return data !== undefined;
  }, []);

  type TextsStatus = NotificationSettings['texts'];
  type Result = { ok: true } | { ok: false; message: string };

  const apply = useCallback((texts: TextsStatus) => setSettings((current) => (current ? { ...current, texts } : current)), []);

  const sendCode = useCallback(async (phone: string): Promise<Result> => {
    const { data, error } = await api().POST('/me/phone', { body: { phone } });
    if (data) apply(data);

    return data ? { ok: true } : { ok: false, message: problem(error) };
  }, [apply]);

  const verifyNumber = useCallback(async (phone: string, code: string): Promise<Result> => {
    const { data, error } = await api().POST('/me/phone/verify', { body: { phone, code } });
    if (data) apply(data);

    return data ? { ok: true } : { ok: false, message: problem(error) };
  }, [apply]);

  const turnTexts = useCallback(async (on: boolean): Promise<Result> => {
    const { data, error } = await api().PUT('/me/texts', { body: { on } });
    if (data) apply(data);

    return data ? { ok: true } : { ok: false, message: problem(error) };
  }, [apply]);

  // Email stopped after a bounce or complaint (B6.2d): the person fixed it.
  const resumeEmail = useCallback(async (): Promise<boolean> => {
    const { data } = await api().POST('/me/email/resume');
    if (data) setSettings((current) => (current ? { ...current, email: data } : current));

    return data !== undefined;
  }, []);

  return { settings, failed, save, sendCode, verifyNumber, turnTexts, resumeEmail };
}

/** The server's message for a refused request (already in the person's language). */
function problem(error: unknown): string {
  const message = (error as { message?: unknown } | undefined)?.message;

  return typeof message === 'string' ? message : '';
}

/** The phone's time zone (what quiet hours should follow). */
export const deviceTimezone = (): string => Intl.DateTimeFormat().resolvedOptions().timeZone;
