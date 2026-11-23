import { router, useFocusEffect } from 'expo-router';
import { Download, HardDrive } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import {
  type DownloadSettings,
  downloadSettings,
  LIMITS,
  pending,
  playable,
  removeLesson,
  type SavedLesson,
  savedLessons,
  setDownloadSettings,
  syncDownloads,
  usedBytes,
} from '@/lib/offline';
import { Button, Card, EmptyState, Icon, ListRow, PillGroup, ProgressBar, Screen, SectionTitle, SwitchRow, Text, TitleBar, useUndoToast } from '@/ui';

const GB = 1024 * 1024 * 1024;
const size = (bytes: number) => (bytes >= GB ? `${(bytes / GB).toFixed(1)} GB` : `${Math.max(1, Math.round(bytes / 1024 / 1024))} MB`);
const daysLeft = (iso: string) => Math.max(0, Math.ceil((new Date(iso).getTime() - Date.now()) / 86_400_000));

/**
 * Downloads (candidate screen 34, A8.2b): study offline without filling the
 * phone. Storage used of the limit (1, 2 or 5 GB), Smart downloads (the
 * next two lessons on Wi-Fi, removed once done), and each saved lesson with
 * its size and how long it works before the phone must go online. Opening
 * the screen online is that check: lessons whose access ended are removed.
 */
export default function DownloadsScreen() {
  const { t } = useTranslation();
  const showToast = useUndoToast();
  const [saved, setSaved] = useState<SavedLesson[]>(savedLessons);
  const [settings, setSettings] = useState<DownloadSettings>(downloadSettings);

  useFocusEffect(
    useCallback(() => {
      void syncDownloads()
        .then((removed) => {
          setSaved(savedLessons());
          if (removed.length > 0) showToast({ message: t('player.downloads.removedEnded', { count: removed.length }) });
        })
        .catch(() => null);
    }, [showToast, t]),
  );

  const change = (next: Partial<DownloadSettings>) => setSettings(setDownloadSettings(next));
  const used = usedBytes();
  const limit = settings.limitGb * GB;

  return (
    <Screen>
      <TitleBar title={t('player.downloads.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('player.downloads.purpose')}</Text>

      <Card>
        <Text weight="semibold">{t('player.downloads.used', { used: size(used), limit: `${settings.limitGb} GB` })}</Text>
        <ProgressBar value={Math.round((100 * used) / limit)} label={t('player.downloads.storage')} />
        <PillGroup
          label={t('player.downloads.limit')}
          value={String(settings.limitGb)}
          options={LIMITS.map((gb) => ({ value: String(gb), label: `${gb} GB` }))}
          onChange={(v) => change({ limitGb: Number(v) as DownloadSettings['limitGb'] })}
        />
      </Card>

      <SwitchRow label={t('player.downloads.smart')} description={t('player.downloads.smartHint')} value={settings.smart} onValueChange={(smart) => change({ smart })} />
      <SwitchRow label={t('player.downloads.wifiOnly')} description={t('player.downloads.wifiOnlyHint')} value={settings.wifiOnly} onValueChange={(wifiOnly) => change({ wifiOnly })} />

      <SectionTitle>{t('player.downloads.lessons')}</SectionTitle>
      {saved.length === 0 ? (
        <EmptyState icon={<Icon icon={Download} color="action" size={32} />} title={t('player.downloads.emptyTitle')} description={t('player.downloads.empty')} />
      ) : (
        <View style={styles.list}>
          {saved.map((s) => {
            const ok = playable(s);
            const waiting = pending(s.grant).length;

            return (
              <Card key={s.grant}>
                <ListRow
                  icon={HardDrive}
                  tone={ok ? 'success' : 'warning'}
                  title={s.title}
                  subtitle={[
                    s.course,
                    size(s.bytes),
                    ok ? t('player.downloads.daysLeft', { count: daysLeft(s.expiresAt) }) : t('player.downloads.goOnline'),
                    waiting > 0 ? t('player.downloads.unsent') : null,
                  ]
                    .filter(Boolean)
                    .join(' · ')}
                  onPress={ok ? () => router.push({ pathname: '/learn/[id]', params: { id: String(s.lessonId) } }) : undefined}
                />
                <Button
                  label={t('player.downloads.remove')}
                  variant="secondary"
                  onPress={() =>
                    void removeLesson(s.grant).then(() => {
                      setSaved(savedLessons());
                      showToast({ message: t('player.downloads.removed', { title: s.title }) });
                    })
                  }
                />
              </Card>
            );
          })}
        </View>
      )}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  list: { gap: theme.space.sm },
}));
