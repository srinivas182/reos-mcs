/**
 * The daily study reminder from the 60-second setup (journey screen 8): a
 * local notification at the chosen time. Permission is asked only after a
 * time is picked.
 */
import * as Notifications from 'expo-notifications';
import { AndroidImportance } from 'expo-notifications';
import { Platform } from 'react-native';

const IDENTIFIER = 'reos.daily-study';

/** Asks for permission (once) and schedules the reminder; false if declined. */
export async function scheduleDailyReminder(time: string, title: string, body: string): Promise<boolean> {
  const [hour, minute] = time.split(':').map(Number);

  if (hour === undefined || minute === undefined || Number.isNaN(hour) || Number.isNaN(minute)) {
    return false;
  }

  const current = await Notifications.getPermissionsAsync();
  const granted = current.granted || (await Notifications.requestPermissionsAsync()).granted;

  if (!granted) {
    return false;
  }

  if (Platform.OS === 'android') {
    await Notifications.setNotificationChannelAsync('study', { name: title, importance: AndroidImportance.DEFAULT });
  }

  await Notifications.cancelScheduledNotificationAsync(IDENTIFIER).catch(() => undefined);
  await Notifications.scheduleNotificationAsync({
    identifier: IDENTIFIER,
    content: { title, body },
    trigger: { type: Notifications.SchedulableTriggerInputTypes.DAILY, hour, minute, channelId: 'study' },
  });

  return true;
}

export async function cancelDailyReminder(): Promise<void> {
  await Notifications.cancelScheduledNotificationAsync(IDENTIFIER).catch(() => undefined);
}
