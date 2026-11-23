import { router, useFocusEffect, useLocalSearchParams } from 'expo-router';
import type { LucideIcon } from 'lucide-react-native';
import { BookOpen, CircleCheck, ClipboardCheck, ListChecks, RotateCcw, Target } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { ActivityIndicator, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';

import { asTopic, continuePlan, loadPlan, openStep, type PlanStep, type TopicPlan } from '@/lib/practice';
import { Button, Callout, Card, ListRow, ProgressBar, Screen, SectionTitle, Text, TitleBar } from '@/ui';

const icons: Record<string, LucideIcon> = { lesson: BookOpen, review: ListChecks, practice: Target, flashcards: RotateCcw, retest: ClipboardCheck };

/**
 * Fix a weak topic (candidate screen 46, A9.2b): now against the goal, 4 to
 * 6 steps with their minutes (micro-lessons, the misses with why,
 * practice, a retest with new questions), Continue to the next, and a done
 * step to review it. The topic leaves the weak list at 70%.
 */
export default function FixScreen() {
  const { t } = useTranslation();
  const { theme } = useUnistyles();
  const { id, misses } = useLocalSearchParams<{ id: string; misses?: string }>();
  const planId = Number(id);
  const [plan, setPlan] = useState<TopicPlan | null | undefined>(undefined);
  const [busy, setBusy] = useState(false);

  useFocusEffect(
    useCallback(() => {
      let active = true;
      void loadPlan(planId)
        .catch(() => null)
        .then((found) => active && setPlan(found));

      return () => {
        active = false;
      };
    }, [planId]),
  );

  const topic = plan ? asTopic(plan.topic) : null;
  const name = topic ? t(`practice.topics.${topic}`) : '';
  const go = async () => {
    setBusy(true);
    const next = await continuePlan(planId).catch(() => null);
    setBusy(false);
    if (next) openStep(planId, next);
  };
  const review = (step: PlanStep) => {
    if (step.lesson) router.push({ pathname: '/learn/[id]', params: { id: String(step.lesson.id), ...(step.lesson.seconds !== null ? { t: String(step.lesson.seconds) } : {}) } });
    else if (step.results_id !== null) router.push({ pathname: '/practice/results/[id]', params: { id: String(step.results_id) } });
    else if (step.attempt_id !== null) router.push({ pathname: '/practice/[id]', params: { id: String(step.attempt_id) } });
  };

  return (
    <Screen>
      <TitleBar title={plan ? t('practice.fix.title', { topic: name }) : t('practice.title')} onBack={() => router.back()} />
      {plan === undefined ? (
        <ActivityIndicator color={theme.colors.action} />
      ) : plan === null ? (
        <Callout tone="danger" title={t('practice.errors.save')} />
      ) : (
        <>
          <Text tone="muted">{t('practice.fix.description')}</Text>
          <Card>
            <Text variant="title">{plan.now === null ? t('practice.fix.notYet') : t('practice.fix.now', { percent: plan.now })}</Text>
            <Text tone="muted">{[t('practice.fix.goal', { percent: plan.goal }), plan.start !== null ? t('practice.fix.started', { percent: plan.start }) : null].filter(Boolean).join(' · ')}</Text>
            <ProgressBar value={plan.now ?? 0} label={t('practice.fix.goal', { percent: plan.goal })} />
          </Card>
          {plan.completed ? (
            <Callout tone="success" title={t('practice.fix.completedTitle')} body={plan.weak ? t('practice.fix.completedWeak', { goal: plan.goal }) : t('practice.fix.completedStrong', { topic: name })} />
          ) : (
            <View style={styles.gap}>
              <Button label={t('practice.fix.continue')} loading={busy} onPress={() => void go()} fullWidth />
              <Text tone="muted">{t('practice.fix.left', { count: plan.minutes })}</Text>
            </View>
          )}
          {plan.steps.map((step) => (
            <ListRow
              key={step.n}
              icon={step.status === 'done' ? CircleCheck : (icons[step.kind] ?? Target)}
              tone={step.status === 'done' ? 'success' : step.status === 'current' ? 'info' : 'neutral'}
              title={step.kind === 'lesson' && step.lesson ? t('practice.fix.steps.lesson', { title: step.lesson.title }) : t(`practice.fix.steps.${step.kind === 'review' || step.kind === 'practice' || step.kind === 'flashcards' || step.kind === 'retest' ? step.kind : 'practice'}`)}
              subtitle={`${t('practice.fix.minutes', { count: step.minutes })} · ${t(`practice.fix.status.${step.status}`)}`}
              onPress={step.status === 'done' && (step.lesson || step.results_id !== null || step.attempt_id !== null) ? () => review(step) : undefined}
            />
          ))}
          {plan.misses.length > 0 ? (
            <>
              <SectionTitle>{t('practice.fix.missesTitle')}</SectionTitle>
              {(misses === '1' ? plan.misses : plan.misses.slice(0, 3)).map((m) => (
                <Card key={m.id}>
                  <Text weight="semibold">{m.prompt}</Text>
                  <Text>{t('practice.fix.answer', { answer: m.options[m.right] ?? '' })}</Text>
                  <Text tone="muted">{m.explanation}</Text>
                  <Text variant="caption" tone="muted">
                    {t('player.checkin.source', { source: m.source })}
                  </Text>
                </Card>
              ))}
            </>
          ) : null}
        </>
      )}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  gap: { gap: theme.space.xs },
}));
