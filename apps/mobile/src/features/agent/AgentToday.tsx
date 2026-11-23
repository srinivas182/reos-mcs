import { router } from 'expo-router';
import { CalendarClock, Check, CircleAlert, CircleCheck, ListChecks, Sun, Sunrise, Timer } from 'lucide-react-native';
import { useState } from 'react';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { planRoute, tickTask, type AgentToday as Today, type DayOneCode, type TodayItem, type TodayStatKey } from '@/lib/agent';
import { setDone } from '@/lib/todos';
import { Card, Chip, EmptyState, Icon, IconButton, ListRow, SectionTitle, Text, useUndoToast } from '@/ui';
import type { Tone } from '@/ui/tones';

type Reason = 'overdue' | 'deadline' | 'appointment' | 'follow_up' | 'plan';

const TONE: Record<Reason, Tone> = { overdue: 'danger', deadline: 'warning', appointment: 'info', follow_up: 'neutral', plan: 'success' };
const ICON = { overdue: CircleAlert, deadline: Timer, appointment: CalendarClock, follow_up: CircleCheck, plan: ListChecks };

/**
 * Today for agents on the phone (A11.1b; agent screens 14 and 15): the
 * mode's stats, then one ranked list from every tool with each item's
 * source, Done with Undo, and the three day-one steps until there are 20
 * contacts or a connected tool.
 */
export function AgentToday({ today, onChanged }: { today: Today; onChanged: () => void }) {
  const { t, i18n } = useTranslation();
  const toast = useUndoToast();
  const [hidden, setHidden] = useState<string[]>([]);
  const items = today.items.filter((item) => !hidden.includes(item.key));

  const mark = async (item: TodayItem, done: boolean) => {
    if (item.plan) await tickTask(item.plan.code, item.plan.week, done);
    else if (item.todo_id !== null) await setDone({ id: item.todo_id, clientId: null }, done);
  };
  const complete = async (item: TodayItem) => {
    setHidden((h) => [...h, item.key]);
    await mark(item, true);
    toast({
      message: t('agents.plan.ticked'),
      onUndo: async () => {
        await mark(item, false);
        setHidden((h) => h.filter((k) => k !== item.key));
        onChanged();
      },
    });
  };
  const when = (item: TodayItem) => {
    if (item.at === null) return null;
    const options: Intl.DateTimeFormatOptions = item.all_day || item.reason === 'overdue' ? { month: 'short', day: 'numeric' } : { timeStyle: 'short' };
    return new Intl.DateTimeFormat(i18n.language, options).format(new Date(item.at));
  };

  return (
    <View style={styles.section}>
      <View style={styles.stats}>
        {today.stats.map((s) => (
          <Card key={s.key} style={styles.stat}>
            <Text tone="muted">{t(`agents.today.stats.${s.key as TodayStatKey}`)}</Text>
            <Text variant="title">{s.target === null ? String(s.value) : t('agents.today.statOf', { value: s.value, target: s.target })}</Text>
          </Card>
        ))}
      </View>
      {today.day_one ? (
        <Card style={styles.dayOne}>
          <Text variant="title">{t('agents.today.dayOne.title')}</Text>
          <Text tone="muted">{t('agents.today.dayOne.body')}</Text>
          {today.day_one.map((step) => {
            const route = planRoute(step.link);
            return (
              <ListRow
                key={step.code}
                icon={step.done ? CircleCheck : Sun}
                tone={step.done ? 'success' : 'info'}
                title={t(`agents.today.dayOne.steps.${step.code as DayOneCode}`)}
                subtitle={t('agents.today.dayOne.minutes', { minutes: step.minutes })}
                onPress={route && !step.done ? () => router.push(route) : undefined}
              />
            );
          })}
        </Card>
      ) : null}
      <SectionTitle>{t('agents.today.title')}</SectionTitle>
      {items.length === 0 && !today.day_one ? (
        <EmptyState icon={<Icon icon={CircleCheck} color="action" size={32} />} title={t('agents.today.emptyTitle')} description={t('agents.today.emptyBody')} />
      ) : (
        items.map((item) => {
          const reason = item.reason as Reason;
          return (
            <ListRow
              key={item.key}
              icon={ICON[reason]}
              tone={TONE[reason]}
              title={item.title}
              subtitle={[when(item), item.detail, t('agents.today.from', { source: item.source })].filter(Boolean).join(' · ')}
              trailing={
                <View style={styles.trailing}>
                  <Chip label={t(`agents.today.reasons.${reason}`)} tone={TONE[reason]} />
                  {item.kind === 'event' ? null : <IconButton icon={Check} label={`${t('agents.today.markDone')}: ${item.title}`} onPress={() => void complete(item)} />}
                </View>
              }
            />
          );
        })
      )}
      {today.more > 0 ? <Text tone="muted">{t('agents.today.more', { count: today.more })}</Text> : null}
      <ListRow icon={ListChecks} tone="info" title={t('agents.plan.title')} subtitle={t('agents.plan.week', { week: today.plan_week, weeks: 13 })} onPress={() => router.push('/agent/plan')} />
      <ListRow icon={Sunrise} tone="info" title={t('agents.brief.title')} subtitle={t('agents.brief.description')} onPress={() => router.push('/agent/brief')} />
    </View>
  );
}

const styles = StyleSheet.create((theme) => ({
  section: { gap: theme.space.sm },
  stats: { flexDirection: 'row', flexWrap: 'wrap', gap: theme.space.sm },
  stat: { flexGrow: 1, flexBasis: 140, gap: theme.space.xs },
  dayOne: { gap: theme.space.sm },
  trailing: { flexDirection: 'row', alignItems: 'center', gap: theme.space.xs },
}));
