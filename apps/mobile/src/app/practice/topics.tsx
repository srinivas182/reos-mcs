import { router } from 'expo-router';
import { ArrowDownRight, ArrowUpRight, Target } from 'lucide-react-native';
import { useState } from 'react';
import { ActivityIndicator, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';

import { useReadiness } from '@/features/readiness/useReadiness';
import { asTopic, type ReadinessTopic, startPlan } from '@/lib/practice';
import { Button, Callout, Card, Chip, Icon, PillGroup, ProgressBar, Screen, Text, TitleBar } from '@/ui';

type Filter = 'all' | 'national' | 'state';

/**
 * What you're good at (candidate screen 44, A9.2): National, Texas or All;
 * every topic weakest first with its share right and this week's trend once
 * it has 10 answers (older answers count less), and how much fixing the
 * weakest would add to the pass chance. Tap a topic for its fix plan.
 */
export default function GoodAtScreen() {
  const { t } = useTranslation();
  const { theme } = useUnistyles();
  const map = useReadiness();
  const [filter, setFilter] = useState<Filter>('all');
  const name = (topic: string) => {
    const known = asTopic(topic);

    return known ? t(`practice.topics.${known}`) : topic;
  };
  // A topic opens its fix plan (screen 46), under way or new.
  const practise = async (topic: string) => {
    const known = asTopic(topic);
    const plan = known ? await startPlan(known).catch(() => null) : null;
    if (plan) router.push({ pathname: '/practice/fix/[id]', params: { id: String(plan.id) } });
  };

  return (
    <Screen>
      <TitleBar title={t('practice.readinessMap.goodAt.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('practice.readinessMap.goodAt.description')}</Text>
      <PillGroup
        label={t('practice.readinessMap.goodAt.filter')}
        value={filter}
        onChange={setFilter}
        options={(['all', 'national', 'state'] as const).map((value) => ({ value, label: t(`practice.readinessMap.goodAt.${value}`) }))}
      />
      {map === undefined ? (
        <ActivityIndicator color={theme.colors.action} />
      ) : map === null ? null : (
        <>
          {map.gain && map.gain.points > 0 ? (
            <Callout
              tone="info"
              title={t('practice.readinessMap.gain', { topic: name(map.gain.topic), points: map.gain.points, portion: t(`practice.portions.${map.gain.portion === 'state' ? 'state' : 'national'}.title`) })}
              action={<Button label={t('practice.readinessMap.fixWeakest')} onPress={() => map.gain && void practise(map.gain.topic)} />}
            />
          ) : null}
          {map.topics
            .filter((x) => filter === 'all' || x.portion === filter)
            .map((x) => (
              <TopicCard key={x.topic} topic={x} name={name(x.topic)} onPractise={() => void practise(x.topic)} />
            ))}
        </>
      )}
    </Screen>
  );
}

function TopicCard({ topic, name, onPractise }: { topic: ReadinessTopic; name: string; onPractise: () => void }) {
  const { t } = useTranslation();
  const shown = topic.shown && topic.percent !== null;
  const trend = topic.trend;

  return (
    <Card accessibilityLabel={t('practice.fix.start', { topic: name })} onPress={onPractise}>
      <View style={styles.head}>
        <Icon icon={Target} color={topic.weak ? 'toneWarningFg' : 'toneSuccessFg'} />
        <Text weight="bold" style={styles.flex}>
          {name}
        </Text>
        {topic.weak ? <Chip tone="warning" label={t('practice.readinessMap.goodAt.weak')} /> : null}
      </View>
      <Text tone="muted">
        {shown ? t('practice.readinessMap.goodAt.percent', { percent: topic.percent }) : topic.answers === 0 ? t('practice.readinessMap.goodAt.noneYet') : t('practice.readinessMap.goodAt.needMore', { count: topic.answers })}
      </Text>
      {shown ? <ProgressBar value={topic.percent ?? 0} label={t('practice.readinessMap.goodAt.percent', { percent: topic.percent })} color={topic.weak ? 'toneWarningFg' : 'toneSuccessFg'} /> : null}
      {trend !== null ? (
        <View style={styles.head}>
          {trend !== 0 ? <Icon icon={trend > 0 ? ArrowUpRight : ArrowDownRight} color="textMuted" size={18} /> : null}
          <Text variant="caption" tone="muted">
            {trend > 0 ? t('practice.readinessMap.goodAt.trendUp', { count: trend }) : trend < 0 ? t('practice.readinessMap.goodAt.trendDown', { count: -trend }) : t('practice.readinessMap.goodAt.trendFlat')}
          </Text>
        </View>
      ) : null}
    </Card>
  );
}

const styles = StyleSheet.create((theme) => ({
  head: { flexDirection: 'row', alignItems: 'center', gap: theme.space.xs },
  flex: { flex: 1 },
}));
