import { router, useLocalSearchParams } from 'expo-router';
import { Plus } from 'lucide-react-native';
import { useEffect, useState } from 'react';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { sendTasks, suggestedTasks } from '@/lib/bookings';
import { Button, Callout, Screen, Text, TextField, TitleBar, useUndoToast } from '@/ui';

/**
 * Session notes into a plan (instructor screen i16, A10.2a): the action
 * lines from the shared notes, edited, sent as the student's to-dos.
 */
export default function SendTasksScreen() {
  const { t } = useTranslation();
  const toast = useUndoToast();
  const { uuid, name } = useLocalSearchParams<{ uuid: string; name?: string }>();
  const [tasks, setTasks] = useState<string[]>(['']);
  const [busy, setBusy] = useState(false);
  const [problem, setProblem] = useState<string | null>(null);

  useEffect(() => {
    void suggestedTasks(String(uuid)).then((found) => found.length > 0 && setTasks(found));
  }, [uuid]);

  const filled = tasks.map((x) => x.trim()).filter((x) => x.length >= 2);

  const send = async () => {
    setBusy(true);
    setProblem(null);
    try {
      await sendTasks(String(uuid), filled.map((title) => ({ title, due_date: null })));
      toast({ message: t('bookings.tasks.sent', { name: name ?? '' }) });
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
          <Button label={t('bookings.tasks.send')} loading={busy} disabled={filled.length === 0} fullWidth onPress={() => void send()} />
        </View>
      }
    >
      <TitleBar title={t('bookings.tasks.title', { name: name ?? '' })} onBack={() => router.back()} />
      <Text tone="muted">{t('bookings.tasks.intro')}</Text>
      {tasks.map((task, i) => (
        <TextField key={i} label={t('bookings.tasks.label', { n: i + 1 })} value={task} maxLength={200} onChangeText={(value) => setTasks(tasks.map((x, j) => (j === i ? value : x)))} />
      ))}
      {tasks.length < 10 ? <Button variant="secondary" icon={Plus} label={t('bookings.tasks.add')} onPress={() => setTasks([...tasks, ''])} /> : null}
      {problem ? <Callout tone="danger" title={problem} live /> : null}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  footer: { paddingHorizontal: theme.m.gutter, paddingBottom: theme.space.xl },
}));
