import { router, useLocalSearchParams } from 'expo-router';
import { Plus } from 'lucide-react-native';
import { useEffect, useState } from 'react';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { sendTasks } from '@/lib/bookings';
import { loadWrapUp } from '@/lib/mentoring';
import { Button, Callout, Screen, Text, TextField, TitleBar, useUndoToast } from '@/ui';

type Task = { title: string; due_date: string | null };

/**
 * Session wrap-up (instructor screen i16, A10.2a; mentor screen 27,
 * A13.1b): a summary and the action items with dates from the shared
 * notes (Claude, or the notes' action lines a week out), edited, sent as
 * the student's or mentee's to-dos with reminders.
 */
export default function SendTasksScreen() {
  const { t, i18n } = useTranslation();
  const toast = useUndoToast();
  const { uuid, name } = useLocalSearchParams<{ uuid: string; name?: string }>();
  const [summary, setSummary] = useState('');
  const [tasks, setTasks] = useState<Task[]>([{ title: '', due_date: null }]);
  const [busy, setBusy] = useState(false);
  const [problem, setProblem] = useState<string | null>(null);

  useEffect(() => {
    void loadWrapUp(String(uuid)).then((draft) => {
      if (!draft) return;
      setSummary(draft.summary);
      if (draft.tasks.length > 0) setTasks(draft.tasks);
    });
  }, [uuid]);

  const filled = tasks.map((x) => ({ ...x, title: x.title.trim() })).filter((x) => x.title.length >= 2);
  const day = (date: string) => new Intl.DateTimeFormat(i18n.language, { weekday: 'short', month: 'short', day: 'numeric', timeZone: 'UTC' }).format(new Date(`${date}T12:00:00Z`));

  const send = async () => {
    setBusy(true);
    setProblem(null);
    try {
      await sendTasks(String(uuid), filled, summary.trim());
      toast({ message: t('mentoring.workspace.sent', { name: name ?? '' }) });
      router.back();
    } catch (e) {
      setProblem(e instanceof Error ? e.message : String(e));
    } finally {
      setBusy(false);
    }
  };

  return (
    <Screen
      footer={
        <View style={styles.footer}>
          <Button label={t('mentoring.workspace.send')} loading={busy} disabled={filled.length === 0} fullWidth onPress={() => void send()} />
        </View>
      }
    >
      <TitleBar title={t('mentoring.workspace.wrapUpTitle')} onBack={() => router.back()} />
      <Text tone="muted">{t('bookings.tasks.intro')}</Text>
      <TextField label={t('mentoring.workspace.summary')} value={summary} maxLength={2000} multiline onChangeText={setSummary} />
      <Text weight="semibold">{t('mentoring.workspace.actions')}</Text>
      {tasks.map((task, i) => (
        <View key={i} style={styles.task}>
          <TextField label={t('bookings.tasks.label', { n: i + 1 })} value={task.title} maxLength={200} onChangeText={(value) => setTasks(tasks.map((x, j) => (j === i ? { ...x, title: value } : x)))} />
          {task.due_date ? (
            <Text variant="caption" tone="muted">
              {`${t('mentoring.workspace.goalDue')}: ${day(task.due_date)}`}
            </Text>
          ) : null}
        </View>
      ))}
      {tasks.length < 10 ? <Button variant="secondary" icon={Plus} label={t('bookings.tasks.add')} onPress={() => setTasks([...tasks, { title: '', due_date: null }])} /> : null}
      {problem ? <Callout tone="danger" title={problem} live /> : null}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  footer: { paddingHorizontal: theme.m.gutter, paddingBottom: theme.space.xl },
  task: { gap: theme.space.xs },
}));
