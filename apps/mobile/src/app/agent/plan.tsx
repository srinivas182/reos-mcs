import { router } from 'expo-router';
import { Circle, CircleCheck, HandHeart, Target } from 'lucide-react-native';
import { useState } from 'react';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { makePromise, planRoute, tickTask, usePlan, type PlanPhase, type PlanTask, type PlanTaskCode, type ScoreMetric } from '@/lib/agent';
import { Button, Callout, Card, EmptyState, Icon, IconButton, ListRow, ProgressBar, Screen, SectionTitle, Text, TitleBar, useUndoToast } from '@/ui';

/**
 * My 90-day plan on the phone (A11.1b; agent screens 10 and 110): the week
 * and phase, this week's tasks to tick off (with Undo), the First-Closing
 * Promise for Launch, and the weekly scorecard with the one thing to fix.
 */
export default function PlanScreen() {
  const { t, i18n } = useTranslation();
  const toast = useUndoToast();
  const { plan, setPlan, scorecard } = usePlan();
  const [problem, setProblem] = useState<string | null>(null);

  if (plan === null) return <Screen>{null}</Screen>;

  const run = async (work: () => ReturnType<typeof makePromise>) => {
    try {
      setPlan(await work());
      setProblem(null);
    } catch (e) {
      setProblem(e instanceof Error ? e.message : String(e));
    }
  };
  const tick = async (task: PlanTask) => {
    const done = !task.done;
    await run(() => tickTask(task.code, task.week, done));
    toast({ message: t(done ? 'agents.plan.ticked' : 'agents.plan.unticked'), onUndo: () => run(() => tickTask(task.code, task.week, !done)) });
  };
  const current = plan.phases.find((p) => p.current);
  const percent = plan.total > 0 ? Math.round((plan.done / plan.total) * 100) : 0;
  const open = plan.tasks.filter((task) => !task.done);

  return (
    <Screen>
      <TitleBar title={t('agents.plan.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('agents.plan.description')}</Text>
      {problem ? <Callout tone="danger" title={problem} live /> : null}
      <Card style={styles.card}>
        <Text variant="title">{t('agents.plan.week', { week: plan.week, weeks: plan.weeks })}</Text>
        {current ? <Text>{t('agents.plan.phase', { phase: t(`agents.plan.phases.${current.code as PlanPhase}`) })}</Text> : null}
        <ProgressBar value={percent} label={t('agents.plan.progress', { done: plan.done, total: plan.total })} />
        <Text tone="muted">{t('agents.plan.progress', { done: plan.done, total: plan.total })}</Text>
      </Card>
      {plan.goal === 'launch' ? (
        <Card style={styles.card}>
          <Text variant="title">{t('agents.plan.promise.title')}</Text>
          <Text tone="muted">{t('agents.plan.promise.body')}</Text>
          {plan.promised ? <Text>{t('agents.plan.promise.made')}</Text> : <Button icon={HandHeart} label={t('agents.plan.promise.action')} onPress={() => void run(makePromise)} />}
        </Card>
      ) : null}
      <SectionTitle>{t('agents.plan.thisWeek')}</SectionTitle>
      {open.length === 0 ? <EmptyState icon={<Icon icon={CircleCheck} color="action" size={32} />} title={t('agents.plan.emptyTitle')} description={t('agents.plan.emptyBody')} /> : null}
      {plan.tasks.map((task) => {
        const name = t(`agents.plan.tasks.${task.code as PlanTaskCode}`);
        const route = planRoute(task.link);
        return (
          <ListRow
            key={`${task.code}-${task.week}`}
            icon={task.done ? CircleCheck : Circle}
            tone={task.done ? 'success' : 'info'}
            title={name}
            subtitle={[task.repeats ? t('agents.plan.repeats') : null, task.auto ? t('agents.plan.auto') : null].filter(Boolean).join(' · ') || undefined}
            onPress={route && !task.done ? () => router.push(route) : undefined}
            trailing={task.auto ? null : <IconButton icon={task.done ? CircleCheck : Circle} label={`${t('agents.plan.done')}: ${name}`} onPress={() => void tick(task)} />}
          />
        );
      })}
      {scorecard ? (
        <>
          <SectionTitle>{t('agents.scorecard.title')}</SectionTitle>
          <Text tone="muted">{t('agents.scorecard.weekOf', { date: new Intl.DateTimeFormat(i18n.language, { month: 'long', day: 'numeric' }).format(new Date(`${scorecard.week_of}T12:00:00`)) })}</Text>
          {scorecard.metrics.map((m) => (
            <View key={m.key} style={styles.metric}>
              <Text>
                {t(`agents.scorecard.metrics.${m.key as ScoreMetric}`)} · {t('agents.scorecard.value', { done: m.done, target: m.target })}
              </Text>
              <ProgressBar value={m.target > 0 ? (m.done / m.target) * 100 : 100} label={t(`agents.scorecard.metrics.${m.key as ScoreMetric}`)} color={m.key === scorecard.fix ? 'danger' : 'action'} />
            </View>
          ))}
          <Callout tone={scorecard.fix ? 'warning' : 'success'} title={t('agents.scorecard.fixTitle')} body={scorecard.fix ? t(`agents.scorecard.fix.${scorecard.fix as ScoreMetric}`) : t('agents.scorecard.onTrack')} />
        </>
      ) : null}
      <ListRow icon={Target} tone="info" title={t('agents.plan.targets')} subtitle={(['conversations', 'appointments', 'clients', 'ce_hours'] as const).map((m) => `${t(`agents.scorecard.metrics.${m}`)} ${plan.targets[m] ?? 0}`).join(' · ')} />
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  card: { gap: theme.space.sm },
  metric: { gap: theme.space.xs },
}));
