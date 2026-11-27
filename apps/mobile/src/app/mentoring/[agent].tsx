import { router, useFocusEffect, useLocalSearchParams } from 'expo-router';
import { CalendarCheck, NotebookPen, Target, Trash2 } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { slotText } from '@/lib/bookings';
import { addMenteeGoal, addMenteeNote, loadMentee, type MenteeDetail, removeMenteeNote } from '@/lib/mentoring';
import { Button, Callout, Card, IconButton, ListRow, Screen, SectionTitle, Text, TextField, TitleBar, useUndoToast } from '@/ui';

const isoDay = (d: Date) => d.toISOString().slice(0, 10);

/**
 * Mentee detail (mentor screen 24; A13.1b): mode and week, plan progress,
 * this week's scorecard, deals, sessions together, goals set together
 * (the mentee's to-dos) and the mentor's private notes. Never the
 * mentee's contacts or clients.
 */
export default function MenteeScreen() {
  const { t, i18n } = useTranslation();
  const toast = useUndoToast();
  const { agent } = useLocalSearchParams<{ agent: string }>();
  const [detail, setDetail] = useState<MenteeDetail | null>(null);
  const [note, setNote] = useState('');
  const [goal, setGoal] = useState('');
  const [busy, setBusy] = useState(false);
  const [problem, setProblem] = useState<string | null>(null);
  const id = Number(agent);

  const load = useCallback(() => void loadMentee(id).then(setDetail), [id]);
  useFocusEffect(load);

  const act = async (work: () => Promise<void>, done?: () => void) => {
    setBusy(true);
    setProblem(null);
    try {
      await work();
      done?.();
      load();
    } catch (e) {
      setProblem(e instanceof Error ? e.message : String(e));
    } finally {
      setBusy(false);
    }
  };

  if (!detail) return <Screen>{null}</Screen>;

  const m = detail.mentee;

  return (
    <Screen>
      <TitleBar title={m.name} onBack={() => router.back()} />
      <Text tone="muted">{[t(`mentoring.goals.${m.goal as 'launch'}`), m.assigned ? t('mentoring.workspace.assigned') : null, t(`mentoring.workspace.signals.${m.signal}`)].filter(Boolean).join(' · ')}</Text>

      <Card style={styles.gap}>
        <Text weight="semibold">{t('mentoring.workspace.plan')}</Text>
        <Text>{t('mentoring.workspace.planWeek', { week: detail.plan.week, weeks: detail.plan.weeks, done: detail.plan.done, total: detail.plan.total })}</Text>
        <Text weight="semibold">{t('mentoring.workspace.scorecard')}</Text>
        {detail.scorecard.map((s) => (
          <Text key={s.key}>{`${t(`mentoring.workspace.metrics.${s.key as 'conversations'}`)}: ${t('mentoring.workspace.metricValue', { done: s.done, target: s.target })}`}</Text>
        ))}
        <Text weight="semibold">{t('mentoring.workspace.deals')}</Text>
        <Text>{t('mentoring.workspace.dealsValue', detail.deals)}</Text>
      </Card>
      <Text variant="caption" tone="muted">
        {t('mentoring.workspace.privacy')}
      </Text>

      <SectionTitle>{t('mentoring.workspace.goals')}</SectionTitle>
      {detail.goals.length === 0 ? <Text tone="muted">{t('mentoring.workspace.noGoals')}</Text> : null}
      {detail.goals.map((g) => (
        <ListRow key={g.id} icon={Target} tone={g.done ? 'success' : 'info'} title={g.title} subtitle={g.due_date ? `${t('mentoring.workspace.goalDue')}: ${g.due_date}` : undefined} />
      ))}
      <TextField label={t('mentoring.workspace.goalTitle')} value={goal} maxLength={200} onChangeText={setGoal} />
      <Button
        variant="secondary"
        icon={Target}
        label={t('mentoring.workspace.addGoal')}
        disabled={busy || goal.trim().length < 2}
        onPress={() =>
          void act(
            () => addMenteeGoal(id, goal.trim(), isoDay(new Date(Date.now() + 7 * 86400000))),
            () => {
              setGoal('');
              toast({ message: t('mentoring.workspace.goalAdded', { name: m.name }) });
            },
          )
        }
      />

      <SectionTitle>{t('mentoring.workspace.sessions')}</SectionTitle>
      {detail.sessions.map((s) => (
        <ListRow key={s.uuid} icon={CalendarCheck} tone="info" title={s.topic} subtitle={[slotText(s.starts_at, i18n.language), s.summary].filter(Boolean).join(' · ')} />
      ))}

      <SectionTitle>{t('mentoring.workspace.notes')}</SectionTitle>
      <Text variant="caption" tone="muted">
        {t('mentoring.workspace.notesHint')}
      </Text>
      {detail.notes.length === 0 ? <Text tone="muted">{t('mentoring.workspace.noNotes')}</Text> : null}
      {detail.notes.map((n) => (
        <View key={n.id} style={styles.note}>
          <Text style={styles.flex}>{n.body}</Text>
          <IconButton
            icon={Trash2}
            label={t('mentoring.workspace.noteRemoved')}
            onPress={() =>
              void act(
                () => removeMenteeNote(n.id),
                () => toast({ message: t('mentoring.workspace.noteRemoved'), onUndo: () => void removeMenteeNote(n.id, true).then(load) }),
              )
            }
          />
        </View>
      ))}
      <TextField label={t('mentoring.workspace.note')} value={note} maxLength={2000} multiline onChangeText={setNote} />
      <Button variant="secondary" icon={NotebookPen} label={t('mentoring.workspace.addNote')} disabled={busy || note.trim().length < 2} onPress={() => void act(() => addMenteeNote(id, note.trim()), () => setNote(''))} />
      {problem ? <Callout tone="danger" title={problem} live /> : null}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  gap: { gap: theme.space.sm },
  note: { flexDirection: 'row', alignItems: 'center', gap: theme.space.sm },
  flex: { flex: 1 },
}));
