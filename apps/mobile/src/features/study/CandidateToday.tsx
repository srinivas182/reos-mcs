import { router } from 'expo-router';
import type { LucideIcon } from 'lucide-react-native';
import { BookOpen, CircleCheck, ClipboardCheck, Flame, Play, RotateCcw, Target } from 'lucide-react-native';
import { useState } from 'react';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { asTopic, type CandidateToday as Today, type StudyTask, startTest } from '@/lib/practice';
import { ActionCard, Callout, Chip, HeroCard, Icon, ListRow, ProgressRing, SectionTitle, Text } from '@/ui';

const ICONS: Record<StudyTask['kind'], LucideIcon> = { diagnostic: ClipboardCheck, lesson: BookOpen, practice: Target, review: RotateCcw };

/**
 * Today for candidates (candidate screen 12, A8.3): the readiness ring and
 * days to the exam, the streak, Continue (the lesson at its second), and
 * today's tasks, never more than the minutes chosen, each with why. Agents
 * keep their own Today.
 */
export function CandidateToday({ today }: { today: Today }) {
  const { t } = useTranslation();
  const [starting, setStarting] = useState<number | null>(null);

  const open = async (task: StudyTask) => {
    if (task.kind === 'lesson' && task.lesson) {
      router.push({ pathname: '/learn/[id]', params: { id: String(task.lesson.id) } });

      return;
    }
    setStarting(task.id);
    const attempt = await startTest(task.kind === 'diagnostic' ? 'diagnostic' : 'topic', task.kind === 'diagnostic' ? null : asTopic(task.topic)).catch(() => null);
    setStarting(null);
    if (attempt) router.push({ pathname: '/practice/[id]', params: { id: String(attempt.id) } });
  };

  const title = (task: StudyTask) => {
    const topic = asTopic(task.topic);

    if (task.kind === 'lesson') return task.lesson?.title ?? t('practice.tasks.lesson');
    if (task.kind === 'diagnostic') return t('practice.tasks.diagnostic');

    return t(task.kind === 'review' ? 'practice.tasks.review' : 'practice.tasks.practice', { topic: topic ? t(`practice.topics.${topic}`) : '' });
  };

  return (
    <View style={styles.wrap}>
      <HeroCard style={styles.hero}>
        <ProgressRing value={today.readiness ?? 0} label={today.readiness === null ? t('practice.readiness.unknown') : t('practice.readiness.label', { percent: today.readiness })}>
          <Text variant="title" style={styles.heroText}>
            {today.readiness === null ? '—' : `${today.readiness}%`}
          </Text>
          <Text variant="caption" style={styles.heroMuted}>
            {t('practice.readiness.title')}
          </Text>
        </ProgressRing>
        <View style={styles.flex}>
          <Text variant="lead" style={styles.heroText}>
            {t('practice.today.daysShort', { count: today.days_to_exam })}
          </Text>
          <Text style={styles.heroMuted}>{today.readiness === null ? t('practice.readiness.takeDiagnostic') : today.weakest.length > 0 ? t('practice.today.focus', { topic: t(`practice.topics.${asTopic(today.weakest[0]) ?? 'agency'}`) }) : t('practice.today.onTrack')}</Text>
          <Chip tone="warning" icon={Flame} label={String(today.streak)} accessibilityLabel={t('practice.today.streak', { count: today.streak })} />
        </View>
      </HeroCard>

      {today.continue ? (
        <ActionCard
          accessibilityLabel={t('practice.today.continue', { title: today.continue.title })}
          onPress={() =>
            router.push({
              pathname: '/learn/[id]',
              params: { id: String(today.continue?.lesson_id), ...(today.continue && today.continue.position > 0 ? { t: String(today.continue.position) } : {}) },
            })
          }
        >
          <View style={styles.flex}>
            <Text variant="caption" weight="bold" style={styles.onActionMuted}>
              {t('practice.today.continueLabel')}
            </Text>
            <Text variant="lead" tone="onAction">
              {today.continue.title}
            </Text>
            <Text style={styles.onActionMuted}>{today.continue.course}</Text>
          </View>
          <View style={styles.play}>
            <Icon icon={Play} color="action" size={26} filled />
          </View>
        </ActionCard>
      ) : null}

      <SectionTitle>{t('practice.today.plan', { count: today.minutes_per_day })}</SectionTitle>
      {today.behind_minutes > 0 ? <Callout tone="info" title={t('practice.today.behind', { hours: Math.ceil(today.behind_minutes / 60) })} /> : null}
      {today.tasks.length > 0 ? (
        today.tasks.map((task) => (
          <ListRow
            key={task.id}
            icon={task.done ? CircleCheck : ICONS[task.kind]}
            tone={task.done ? 'success' : 'info'}
            title={title(task)}
            subtitle={[t('practice.tasks.minutes', { count: task.minutes }), t(`practice.why.${task.why}`), task.done ? t('practice.tasks.done') : null].filter(Boolean).join(' · ')}
            trailing={starting === task.id ? <Text tone="muted">…</Text> : undefined}
            onPress={() => void open(task)}
          />
        ))
      ) : (
        <Text tone="muted">{t('practice.today.rest')}</Text>
      )}
      <ListRow icon={Target} tone="info" title={t('practice.title')} subtitle={t('practice.description')} onPress={() => router.push('/practice')} />
    </View>
  );
}

const styles = StyleSheet.create((theme) => ({
  wrap: { gap: theme.space.md },
  hero: { flexDirection: 'row', alignItems: 'center', gap: theme.space.lg },
  flex: { flex: 1, gap: 2 },
  heroText: { color: theme.colors.heroText },
  heroMuted: { color: theme.colors.heroMuted },
  onActionMuted: { color: theme.colors.onAction, opacity: 0.9 },
  play: { width: 56, height: 56, borderRadius: 999, backgroundColor: theme.colors.surface, alignItems: 'center', justifyContent: 'center' },
}));
