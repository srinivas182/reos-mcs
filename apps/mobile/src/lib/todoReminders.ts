/**
 * To-do reminders on this phone (B4.3b, ADR 0018): a local notification at
 * the reminder time, with Done and Snooze on the lock screen. Neither opens
 * the app. They're scheduled on the phone, so they ring with no signal.
 *
 * Server push to the person's other devices arrives with B6.2.
 */
import type { TFunction } from 'i18next';
import * as Notifications from 'expo-notifications';
import { AndroidImportance } from 'expo-notifications';
import { useEffect } from 'react';
import { Platform } from 'react-native';

import { i18n } from '@/lib/i18n';
import { setDone } from '@/lib/todos';

export const TODO_CATEGORY = 'reos.todo';
export const DONE_ACTION = 'done';
export const SNOOZE_ACTION = 'snooze';
export const SNOOZE_MINUTES = 10;
const CHANNEL = 'todos';

type ReminderData = { kind: 'todo'; clientId: string | null; id: number | null; title: string };

export const reminderId = (key: string): string => `reos.todo.${key}`;

/** The Done and Snooze buttons and, on Android, the to-do channel (in the language on screen). */
export async function setUpTodoReminders(t: TFunction = i18n.t): Promise<void> {
  await Notifications.setNotificationCategoryAsync(TODO_CATEGORY, [
    { identifier: DONE_ACTION, buttonTitle: t('todos.reminder.done'), options: { opensAppToForeground: false } },
    { identifier: SNOOZE_ACTION, buttonTitle: t('todos.reminder.snooze'), options: { opensAppToForeground: false } },
  ]);

  if (Platform.OS === 'android') {
    await Notifications.setNotificationChannelAsync(CHANNEL, { name: t('todos.reminder.channel'), importance: AndroidImportance.HIGH });
  }
}

/** Asks for permission (the first time a reminder is set) and schedules it; false if declined or the time has passed. */
export async function scheduleTodoReminder(todo: { clientId: string | null; id: number | null; title: string; remindAt: string }, t: TFunction = i18n.t): Promise<boolean> {
  const at = new Date(todo.remindAt);

  if (Number.isNaN(at.getTime()) || at.getTime() <= Date.now()) {
    return false;
  }

  const current = await Notifications.getPermissionsAsync();
  const granted = current.granted || (await Notifications.requestPermissionsAsync()).granted;

  if (!granted) {
    return false;
  }

  await setUpTodoReminders(t);
  await ring(todo, at, t);

  return true;
}

async function ring(todo: { clientId: string | null; id: number | null; title: string }, at: Date, t: TFunction): Promise<void> {
  const data: ReminderData = { kind: 'todo', clientId: todo.clientId, id: todo.id, title: todo.title };

  await Notifications.scheduleNotificationAsync({
    identifier: reminderId(todo.clientId ?? String(todo.id)),
    content: { title: t('todos.reminder.title'), body: todo.title, categoryIdentifier: TODO_CATEGORY, data, sound: 'default' },
    trigger: { type: Notifications.SchedulableTriggerInputTypes.DATE, date: at, channelId: CHANNEL },
  });
}

export async function cancelTodoReminder(key: string | null): Promise<void> {
  if (key !== null) {
    await Notifications.cancelScheduledNotificationAsync(reminderId(key)).catch(() => undefined);
  }
}

function reminderData(response: Notifications.NotificationResponse): ReminderData | null {
  const data = response.notification.request.content.data as Partial<ReminderData> | undefined;

  return data?.kind === 'todo' && typeof data.title === 'string' ? { kind: 'todo', clientId: data.clientId ?? null, id: data.id ?? null, title: data.title } : null;
}

/** Done marks the to-do done (or queues it with no signal); Snooze rings again in 10 minutes. */
export async function handleTodoResponse(response: Notifications.NotificationResponse, now: Date = new Date(), t: TFunction = i18n.t): Promise<void> {
  const todo = reminderData(response);

  if (todo === null) return;

  if (response.actionIdentifier === DONE_ACTION) {
    await setDone({ id: todo.id, clientId: todo.clientId }, true);
  } else if (response.actionIdentifier === SNOOZE_ACTION) {
    await ring(todo, new Date(now.getTime() + SNOOZE_MINUTES * 60_000), t);
  }

  await Notifications.dismissNotificationAsync(response.notification.request.identifier).catch(() => undefined);
}

/** Listens for Done and Snooze (mounted once, in the app's layout). */
export function useTodoReminderActions(): void {
  useEffect(() => {
    // Reminders also show while REOS is open.
    Notifications.setNotificationHandler({
      handleNotification: async () => ({ shouldShowBanner: true, shouldShowList: true, shouldPlaySound: true, shouldSetBadge: false }),
    });
    void setUpTodoReminders().catch(() => undefined);
    const subscription = Notifications.addNotificationResponseReceivedListener((response) => {
      void handleTodoResponse(response);
    });

    return () => subscription.remove();
  }, []);
}
