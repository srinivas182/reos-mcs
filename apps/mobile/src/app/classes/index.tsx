import { router, useFocusEffect, useLocalSearchParams } from 'expo-router';
import { Award, FileCheck, PlayCircle, Users, Video } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { type ClassList, classTime, groupClasses, type LiveClassRow, loadClasses, registerForClass, unregisterFromClass } from '@/lib/classes';
import { Button, Callout, Card, Chip, EmptyState, Screen, SectionTitle, Text, TitleBar, useUndoToast } from '@/ui';

/**
 * Live classes (A10.2b). Students (L12, a course's Live classes): classes
 * for their courses, Register, and Join when it opens. Instructors (i11):
 * this week, next week and past with registrations and CE; they schedule
 * on the web.
 */
export default function ClassesScreen() {
  const { t, i18n } = useTranslation();
  const toast = useUndoToast();
  const params = useLocalSearchParams<{ course?: string }>();
  const course = params.course ? Number(params.course) : undefined;
  const [list, setList] = useState<ClassList | null>(null);
  const [problem, setProblem] = useState<string | null>(null);
  const reload = useCallback(() => {
    void loadClasses(course).then(setList);
  }, [course]);

  useFocusEffect(reload);

  const act = async (work: () => Promise<unknown>) => {
    setProblem(null);
    try {
      await work();
      reload();

      return true;
    } catch (e) {
      setProblem(e instanceof Error ? e.message : String(e));

      return false;
    }
  };

  const cancel = async (row: LiveClassRow) => {
    if (await act(() => unregisterFromClass(row.id))) {
      toast({ message: t('classes.unregistered', { title: row.title }), onUndo: () => void act(() => registerForClass(row.id)) });
    }
  };

  const host = list?.can_schedule ?? false;
  const open = (row: LiveClassRow) => router.push({ pathname: '/sessions/[id]', params: { id: row.id } });

  return (
    <Screen>
      <TitleBar title={t('classes.title')} onBack={() => router.back()} />
      <Text tone="muted">{host ? t('classes.purposeHost') : t('classes.purpose')}</Text>
      {host ? <Callout tone="info" title={t('classes.scheduleOnWeb')} /> : null}
      {problem ? <Callout tone="danger" title={problem} live /> : null}
      {list && list.data.length === 0 ? (
        <EmptyState icon={<Users />} title={host ? t('classes.emptyHost') : t('classes.empty')} description={host ? t('classes.emptyHostBody') : t('classes.emptyBody')} />
      ) : null}
      {groupClasses(list?.data ?? []).map(({ group, rows }) => (
        <View key={group} style={styles.group}>
          <SectionTitle>{t(`classes.groups.${group}`)}</SectionTitle>
          {rows.map((row) => (
            <Card key={row.id}>
              <Text weight="semibold">{row.title}</Text>
              <Text tone="muted">{classTime(row, i18n.language)}</Text>
              <Text variant="caption" tone="muted">
                {[row.course.title, row.me !== 'host' ? t('classes.with', { name: row.host }) : null, t('classes.registered', { count: row.registered, capacity: row.capacity })]
                  .filter(Boolean)
                  .join(' · ')}
              </Text>
              <View style={styles.actions}>
                <Chip tone="neutral" label={t(`sessions.statuses.${row.status}`)} />
                {row.ce ? <Chip tone="success" icon={Award} label={row.ce_hours !== null ? t('classes.ceHours', { count: row.ce_hours }) : t('classes.ce')} /> : null}
              </View>
              <View style={styles.actions}>
                {row.can_join ? <Button icon={Video} label={t('sessions.join')} onPress={() => open(row)} /> : row.me !== null ? <Button variant="secondary" label={t('classes.open')} onPress={() => open(row)} /> : null}
                {/* The CE class report for the host (i13), and the recording for students (A10.2c). */}
                {row.me === 'host' && row.report !== 'none' ? (
                  <Button
                    variant={row.report === 'draft' ? 'primary' : 'secondary'}
                    icon={FileCheck}
                    label={row.report === 'draft' ? t('classes.report.confirm') : t('classes.report.title')}
                    onPress={() => router.push({ pathname: '/classes/[id]/report', params: { id: row.id } })}
                  />
                ) : null}
                {row.has_recording && row.me !== 'host' ? <Button variant="secondary" icon={PlayCircle} label={t('classes.recording')} onPress={() => open(row)} /> : null}
                {row.me === 'registered' && row.status === 'scheduled' ? <Button variant="secondary" label={t('classes.unregister')} onPress={() => void cancel(row)} /> : null}
                {row.me === null ? <Button label={row.full ? t('classes.full') : t('classes.register')} disabled={!row.can_register} onPress={() => void act(() => registerForClass(row.id))} /> : null}
              </View>
            </Card>
          ))}
        </View>
      ))}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  group: { gap: theme.space.sm },
  actions: { flexDirection: 'row', gap: theme.space.sm, flexWrap: 'wrap', alignItems: 'center' },
}));
