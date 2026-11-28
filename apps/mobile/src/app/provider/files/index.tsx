import { router, useFocusEffect } from 'expo-router';
import { FolderOpen } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { loadFiles, type FilesBoard } from '@/lib/files';
import { EmptyState, Icon, ListRow, Screen, SectionTitle, Text, TitleBar } from '@/ui';

/**
 * My files on the phone (B17.1, provider screen 14): the coordinator's
 * active files, nearest deadline first. A file leaves at closing.
 */
export default function MyFilesScreen() {
  const { t, i18n } = useTranslation();
  const [board, setBoard] = useState<FilesBoard | null>(null);

  useFocusEffect(
    useCallback(() => {
      void loadFiles().then(setBoard);
    }, []),
  );

  const day = (d: string) => new Date(`${d}T12:00:00`).toLocaleDateString(i18n.language, { month: 'short', day: 'numeric' });

  return (
    <Screen>
      <TitleBar title={t('coordinators.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('coordinators.purpose')}</Text>
      {board ? <Text>{`${board.counts.active} ${t('coordinators.counts.active')} · ${board.counts.due_this_week} ${t('coordinators.counts.dueThisWeek')} · ${board.counts.missing} ${t('coordinators.counts.missing')}`}</Text> : null}
      <SectionTitle>{t('coordinators.counts.active')}</SectionTitle>
      {board && board.files.length === 0 ? <EmptyState icon={<Icon icon={FolderOpen} color="action" size={32} />} title={t('coordinators.empty')} description={t('coordinators.emptyHint')} /> : null}
      {board?.files.map((f) => (
        <ListRow
          key={f.id}
          icon={FolderOpen}
          tone={f.missing > 0 ? 'warning' : 'info'}
          title={f.property}
          subtitle={[
            f.agent,
            f.next === null ? t('coordinators.noNext') : t('coordinators.next', { label: f.next.label, date: day(f.next.due_on) }),
            f.missing > 0 ? t('coordinators.missing', { count: f.missing }) : t('coordinators.progress', { done: f.progress.done, total: f.progress.total }),
          ].join(' · ')}
          onPress={() => router.push({ pathname: '/provider/files/[share]', params: { share: f.id } })}
        />
      ))}
    </Screen>
  );
}
