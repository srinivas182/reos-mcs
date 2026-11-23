import { router } from 'expo-router';
import type { LucideIcon } from 'lucide-react-native';
import { ClipboardCheck, Dumbbell, Gauge, GraduationCap, Target, Wrench } from 'lucide-react-native';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { asTopic, type Coach, startPlan, startTest } from '@/lib/practice';
import { Button, Card, ListRow, SectionTitle, Text } from '@/ui';

const icons: Record<string, LucideIcon> = { diagnostic: ClipboardCheck, plan: Wrench, drill: Dumbbell, fix: Target, simulation: GraduationCap, readiness: Gauge };

/**
 * The top of the practice hub (candidate screen 38, A9.2b): the smart drill
 * on the three weakest topics (15 questions, misses included), then every
 * mode ranked by what helps most, each with why. `onSimulation` opens the
 * simulation intro.
 */
export function CoachCard({ coach, onSimulation }: { coach: Coach; onSimulation: () => void }) {
  const { t, i18n } = useTranslation();
  const [busy, setBusy] = useState(false);
  const name = (topic: string | null) => {
    const known = asTopic(topic);

    return known ? t(`practice.topics.${known}`) : '';
  };
  const topics = new Intl.ListFormat(i18n.language, { style: 'long', type: 'conjunction' }).format(coach.drill.topics.map(name));
  const open = (id: number) => router.push({ pathname: '/practice/[id]', params: { id: String(id) } });
  const drill = async () => {
    if (coach.drill.open !== null) return open(coach.drill.open);
    setBusy(true);
    const attempt = await startTest('drill').catch(() => null);
    setBusy(false);
    if (attempt) open(attempt.id);
  };
  const go = async (item: Coach['ranked'][number]) => {
    switch (item.mode) {
      case 'diagnostic': {
        const attempt = await startTest('diagnostic').catch(() => null);
        if (attempt) open(attempt.id);
        break;
      }
      case 'plan':
        if (item.plan_id !== null) router.push({ pathname: '/practice/fix/[id]', params: { id: String(item.plan_id) } });
        break;
      case 'fix': {
        const topic = asTopic(item.topic);
        const plan = topic ? await startPlan(topic).catch(() => null) : null;
        if (plan) router.push({ pathname: '/practice/fix/[id]', params: { id: String(plan.id) } });
        break;
      }
      case 'drill':
        await drill();
        break;
      case 'simulation':
        onSimulation();
        break;
      default:
        router.push('/practice/readiness');
    }
  };
  const label = (item: Coach['ranked'][number]) => {
    const plan = coach.plans.find((p) => p.id === item.plan_id);
    if (item.mode === 'plan' && plan) return t('practice.fix.carryOn', { topic: name(plan.topic), done: plan.done, steps: plan.steps });
    if (item.mode === 'fix' && item.topic) return t('practice.fix.start', { topic: name(item.topic) });

    return t(`practice.coach.modes.${item.mode}`);
  };

  return (
    <>
      <Card>
        <Text variant="lead" weight="bold">
          {t('practice.drill.title')}
        </Text>
        <Text tone="muted">{t('practice.drill.description', { count: coach.drill.questions })}</Text>
        <Text>{t('practice.drill.topics', { topics })}</Text>
        {coach.drill.misses > 0 ? <Text tone="muted">{t('practice.drill.misses', { count: coach.drill.misses })}</Text> : null}
        <Button icon={Dumbbell} loading={busy} label={coach.drill.open !== null ? t('practice.drill.carryOn') : t('practice.drill.start')} onPress={() => void drill()} fullWidth />
      </Card>
      <SectionTitle>{t('practice.coach.title')}</SectionTitle>
      {coach.ranked.map((item, i) => (
        <ListRow
          key={`${item.mode}-${item.plan_id ?? item.topic ?? i}`}
          icon={icons[item.mode] ?? Target}
          tone={i === 0 ? 'info' : 'neutral'}
          title={label(item)}
          subtitle={t(`practice.coach.why.${item.why as 'map'}`)}
          onPress={() => void go(item)}
        />
      ))}
    </>
  );
}
