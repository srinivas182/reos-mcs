import { router, useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';
import { Linking, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { deviceTimezone, type KindSetting, useNotificationSettings } from '@/lib/notifications';
import { type PushPermission, pushPermission, turnOffPush, turnOnPush } from '@/lib/push';
import { Button, Callout, Eyebrow, MenuGroup, Screen, SwitchRow, Text, TextField, TitleBar } from '@/ui';

/** The API takes `channels.{kind}.{channel}`; its generated type flattens the keyed object to arrays. */
const channelChange = (kind: string, channel: string, on: boolean) => ({ [kind]: { [channel]: on } }) as unknown as boolean[][];

const GROUPS = ['messages', 'learning', 'organisation', 'tools', 'billing', 'account'] as const;

/**
 * Me › Notifications (B6.2): push on this phone (B6.2b), quiet hours in the
 * phone's time zone (default 9 PM to 7 AM; urgent alerts still arrive), and
 * each kind by channel. Security and account messages can't be turned off.
 */
export default function NotificationSettingsScreen() {
  const { t } = useTranslation();
  const { settings, failed, save } = useNotificationSettings();
  // What the person typed, else what's saved.
  const [startEdit, setStart] = useState<string | null>(null);
  const [endEdit, setEnd] = useState<string | null>(null);
  const zone = deviceTimezone();

  const start = startEdit ?? settings?.quiet.start ?? '21:00';
  const end = endEdit ?? settings?.quiet.end ?? '07:00';

  const [permission, setPermission] = useState<PushPermission | null>(null);
  const [pushFailed, setPushFailed] = useState(false);

  useFocusEffect(
    useCallback(() => {
      void pushPermission()
        .then(setPermission)
        .catch(() => setPermission(null));
    }, []),
  );

  const turnOn = async () => {
    const on = await turnOnPush(true).catch(() => false);
    setPushFailed(!on);
    setPermission(await pushPermission());
  };

  const turnOff = async () => {
    await turnOffPush().catch(() => false);
    setPushFailed(false);
  };

  const name = (kind: KindSetting) => t(`notifications.names.${kind.kind}` as 'notifications.names.message');

  return (
    <Screen>
      <TitleBar title={t('notifications.settingsTitle')} onBack={() => router.back()} />
      {failed ? <Callout tone="danger" title={t('consents.errors.generic')} live /> : null}

      <Eyebrow>{t('notifications.thisPhone.title')}</Eyebrow>
      {permission === 'granted' ? (
        <View style={styles.block}>
          <Text tone="muted">{t('notifications.thisPhone.on')}</Text>
          <Button variant="ghost" label={t('notifications.thisPhone.turnOff')} onPress={() => void turnOff()} />
        </View>
      ) : permission === 'denied' ? (
        <View style={styles.block}>
          <Text tone="muted">{t('notifications.thisPhone.blocked')}</Text>
          <Button variant="secondary" label={t('notifications.thisPhone.openSettings')} onPress={() => void Linking.openSettings()} />
        </View>
      ) : permission === 'undetermined' ? (
        <View style={styles.block}>
          <Text tone="muted">{t('notifications.thisPhone.off')}</Text>
          <Button label={t('notifications.thisPhone.turnOn')} onPress={() => void turnOn()} />
        </View>
      ) : null}
      {pushFailed ? <Callout tone="danger" title={t('notifications.thisPhone.failed')} live /> : null}

      <Eyebrow>{t('notifications.quiet.title')}</Eyebrow>
      <Text tone="muted">{t('notifications.quiet.description')}</Text>
      {settings ? (
        <View style={styles.block}>
          <MenuGroup>
            <SwitchRow label={t('notifications.quiet.on')} value={settings.quiet.enabled} onValueChange={(on) => void save({ quiet: { enabled: on, start, end }, timezone: zone })} />
          </MenuGroup>
          <View style={styles.times}>
            <View style={styles.flex}>
              <TextField label={t('notifications.quiet.start')} value={start} onChangeText={setStart} keyboardType="numbers-and-punctuation" maxLength={5} />
            </View>
            <View style={styles.flex}>
              <TextField label={t('notifications.quiet.end')} value={end} onChangeText={setEnd} keyboardType="numbers-and-punctuation" maxLength={5} />
            </View>
          </View>
          <Text variant="caption" tone="muted">
            {t('notifications.quiet.zone', { zone })}
          </Text>
          <Button variant="secondary" label={t('notifications.quiet.save')} onPress={() => void save({ quiet: { enabled: settings.quiet.enabled, start, end }, timezone: zone })} />
        </View>
      ) : null}

      <Eyebrow>{t('notifications.kinds.title')}</Eyebrow>
      <Text tone="muted">{t('notifications.kinds.description')}</Text>
      {GROUPS.map((group) => {
        const kinds = (settings?.kinds ?? []).filter((k) => k.group === group);

        return kinds.length === 0 ? null : (
          <View key={group} style={styles.block}>
            <Text weight="bold">{t(`notifications.groups.${group}`)}</Text>
            <MenuGroup>
              {kinds.flatMap((kind) =>
                (settings?.channels ?? []).map((channel) => (
                  <SwitchRow
                    key={`${kind.kind}-${channel}`}
                    label={t('notifications.kinds.switch', { kind: name(kind), channel: t(`notifications.channels.${channel}` as 'notifications.channels.inbox') })}
                    description={kind.locked ? t('notifications.kinds.locked') : kind.urgent ? t('notifications.kinds.urgent') : undefined}
                    value={kind.channels[channel] ?? false}
                    onValueChange={(on) => (kind.locked ? undefined : void save({ channels: channelChange(kind.kind, channel, on) }))}
                  />
                )),
              )}
            </MenuGroup>
          </View>
        );
      })}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  block: { gap: theme.space.sm },
  times: { flexDirection: 'row', gap: theme.space.md },
  flex: { flex: 1 },
}));
