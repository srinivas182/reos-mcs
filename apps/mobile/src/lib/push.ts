/**
 * Push notifications on this phone (B6.2b, ADR 0023). Once the person allows
 * notifications, the phone's Expo push token goes to REOS with this sign-in
 * (signing out removes it). A tap opens the screen the push points to; a
 * chat push doesn't show while that chat is already open.
 */
import Constants from 'expo-constants';
import * as Device from 'expo-device';
import * as Notifications from 'expo-notifications';
import { AndroidImportance } from 'expo-notifications';
import { router, useFocusEffect, type Href } from 'expo-router';
import { useCallback, useEffect, useState } from 'react';
import { Platform } from 'react-native';
import { createMMKV } from 'react-native-mmkv';

import { api } from '@/lib/api';
import { i18n } from '@/lib/i18n';

export type PushPermission = 'granted' | 'undetermined' | 'denied';

const storage = createMMKV({ id: 'reos.push' });
const PROMPT_DISMISSED = 'promptDismissed';

/** The screen open right now, so a push for that chat stays quiet. */
let viewing: string | null = null;
/** Taps already followed (the last response survives a re-render). */
const opened = new Set<string>();

export async function pushPermission(): Promise<PushPermission> {
  const permission = await Notifications.getPermissionsAsync();

  if (permission.granted) return 'granted';

  return permission.canAskAgain === false ? 'denied' : 'undetermined';
}

/** Android groups notifications into channels people can tune in system settings. */
async function setUpChannels(): Promise<void> {
  if (Platform.OS !== 'android') return;

  await Notifications.setNotificationChannelAsync('messages', { name: i18n.t('notifications.groups.messages'), importance: AndroidImportance.HIGH });
  await Notifications.setNotificationChannelAsync('alerts', { name: i18n.t('notifications.title'), importance: AndroidImportance.DEFAULT });
}

/**
 * Sends this phone's push token to REOS. With `ask`, asks for permission
 * first (only ever after the person taps Turn on); without it, only renews
 * a token they already allowed.
 */
export async function turnOnPush(ask: boolean): Promise<boolean> {
  if (Device.isDevice !== true) return false;

  let granted = (await Notifications.getPermissionsAsync()).granted;

  if (!granted && ask) {
    granted = (await Notifications.requestPermissionsAsync()).granted;
  }

  if (!granted) return false;

  await setUpChannels();

  const extra = Constants.expoConfig?.extra as { eas?: { projectId?: string } } | undefined;
  const projectId = extra?.eas?.projectId ?? Constants.easConfig?.projectId;
  const { data: token } = await Notifications.getExpoPushTokenAsync(projectId ? { projectId } : undefined);
  const { response } = await api().POST('/me/push-devices', {
    body: { token, platform: Platform.OS === 'ios' ? 'ios' : 'android', name: Device.deviceName ?? Device.modelName ?? null },
  });

  return response.ok;
}

export async function turnOffPush(): Promise<boolean> {
  const { response } = await api().DELETE('/me/push-devices/current');

  return response.ok;
}

/** Where a push from REOS opens (never a to-do reminder, which has its own actions). */
export function pushPath(data: unknown): string | null {
  if (typeof data !== 'object' || data === null) return null;

  const { path, kind } = data as { path?: unknown; kind?: unknown };

  return typeof path === 'string' && path.startsWith('/') && typeof kind === 'string' && kind !== 'todo' ? path : null;
}

/** Whether a push shows while REOS is open: not a chat you're reading. */
export function showWhileOpen(data: unknown): boolean {
  const path = pushPath(data);

  return path === null || !path.startsWith('/messages/') || path !== viewing;
}

export function promptDismissed(): boolean {
  return storage.getBoolean(PROMPT_DISMISSED) === true;
}

export function dismissPrompt(): void {
  storage.set(PROMPT_DISMISSED, true);
}

/** Mounted once in the app's layout. */
export function usePush(signedIn: boolean, pathname: string): void {
  const last = Notifications.useLastNotificationResponse();

  useEffect(() => {
    viewing = pathname;
  }, [pathname]);

  // Tokens change now and then; each start renews it (only if already allowed).
  useEffect(() => {
    if (signedIn) void turnOnPush(false).catch(() => undefined);
  }, [signedIn]);

  // A tap opens the screen it points to, including one that started the app.
  useEffect(() => {
    if (!signedIn || !last || last.actionIdentifier !== Notifications.DEFAULT_ACTION_IDENTIFIER) return;

    const id = last.notification.request.identifier;
    const path = pushPath(last.notification.request.content.data);

    if (path === null || opened.has(id)) return;

    opened.add(id);
    router.push(path as Href);
  }, [last, signedIn]);
}

/**
 * The "Get messages on this phone" card on Today: shown until the person
 * turns push on or says Not now; the system prompt comes only after a tap.
 */
export function usePushPrompt(signedIn: boolean) {
  const [show, setShow] = useState(false);

  useFocusEffect(
    useCallback(() => {
      if (!signedIn || promptDismissed()) {
        setShow(false);

        return;
      }

      void pushPermission()
        .then((permission) => setShow(permission === 'undetermined'))
        .catch(() => setShow(false));
    }, [signedIn]),
  );

  const answer = useCallback(async (yes: boolean) => {
    dismissPrompt();
    setShow(false);
    if (yes) await turnOnPush(true).catch(() => false);
  }, []);

  return { show, answer };
}
