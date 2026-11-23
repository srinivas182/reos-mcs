import { router } from 'expo-router';
import { CalendarCheck, CircleCheck, CircleDashed, Gauge, Target } from 'lucide-react-native';
import { ActivityIndicator, Linking, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';

import { useReadiness } from '@/features/readiness/useReadiness';
import { asTopic, type ReadinessMap, startTest } from '@/lib/practice';
import { Button, Callout, Card, EmptyState, Icon, ListRow, ProgressRing, Screen, SectionTitle, Text, TitleBar } from '@/ui';

/**
 * Readiness map (candidate screen 54, A9.2): the predicted pass chance for
 * each exam portion against the 90% booking line, what booking still needs
 * (Ready to book, screen 56, once both are there), and every topic weakest
 * first; tap one to practise it.
 */
export default function ReadinessScreen() {
  const { t } = useTranslation();
  const { theme } = useUnistyles();
  const map = useReadiness();
  const practise = async (topic: string) => {
    const known = asTopic(topic);
    const attempt = known ? await startTest('topic', known).catch(() => null) : null;
    if (attempt) router.push({ pathname: '/practice/[id]', params: { id: String(attempt.id) } });
  };

  return (
    <Screen>
      <TitleBar title={t('practice.readinessMap.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('practice.readinessMap.description')}</Text>
      {map === undefined ? (
        <ActivityIndicator color={theme.colors.action} />
      ) : map === null || (map.portions.national === null && map.portions.state === null) ? (
        <EmptyState icon={<Icon icon={Gauge} color="action" size={32} />} title={t('practice.readinessMap.emptyTitle')} description={t('practice.readinessMap.empty')} />
      ) : (
        <>
          <View style={styles.rings}>
            {(['national', 'state'] as const).map((portion) => (
              <Portion key={portion} portion={portion} percent={map.portions[portion]} line={map.line} />
            ))}
          </View>
          <Booking booking={map.booking} line={map.line} />
          <SectionTitle>{t('practice.readinessMap.goodAt.title')}</SectionTitle>
          <Button variant="secondary" label={t('practice.readinessMap.goodAt.open')} onPress={() => router.push('/practice/topics')} />
          {map.topics.map((x) => {
            const topic = asTopic(x.topic);
            const name = topic ? t(`practice.topics.${topic}`) : x.topic;

            return (
              <ListRow
                key={x.topic}
                icon={Target}
                tone={x.weak ? 'warning' : x.shown ? 'success' : 'neutral'}
                title={name}
                subtitle={x.shown && x.percent !== null ? t('practice.readinessMap.goodAt.percent', { percent: x.percent }) : x.answers === 0 ? t('practice.readinessMap.goodAt.noneYet') : t('practice.readinessMap.goodAt.needMore', { count: x.answers })}
                onPress={() => void practise(x.topic)}
              />
            );
          })}
        </>
      )}
    </Screen>
  );
}

function Portion({ portion, percent, line }: { portion: 'national' | 'state'; percent: number | null; line: number }) {
  const { t } = useTranslation();
  const name = t(`practice.portions.${portion}.title`);

  return (
    <Card style={styles.portion}>
      <ProgressRing value={percent ?? 0} size={112} color="action" track="border" label={percent === null ? `${name}: ${t('practice.readinessMap.noAnswers')}` : t('practice.readinessMap.chanceLabel', { portion: name, percent })}>
        <Text variant="title">{percent === null ? '—' : `${percent}%`}</Text>
      </ProgressRing>
      <Text weight="bold">{name}</Text>
      <Text variant="caption" tone="muted" style={styles.center}>
        {percent === null ? t('practice.readinessMap.noAnswers') : percent >= line ? t('practice.readinessMap.aboveLine') : t('practice.readinessMap.toGo', { count: line - percent })}
      </Text>
    </Card>
  );
}

function Booking({ booking, line }: { booking: ReadinessMap['booking']; line: number }) {
  const { t } = useTranslation();

  if (booking.ready) {
    return (
      <Callout
        tone="success"
        title={t('practice.readinessMap.booking.readyTitle')}
        body={t('practice.readinessMap.booking.readyBody')}
        action={booking.url ? <Button label={t('practice.readinessMap.booking.book')} icon={CalendarCheck} onPress={() => void Linking.openURL(booking.url ?? '')} /> : undefined}
      />
    );
  }

  const checks = [
    { key: 'national', met: booking.national, text: t('practice.readinessMap.booking.national', { line }) },
    { key: 'state', met: booking.state, text: t('practice.readinessMap.booking.state', { line }) },
    { key: 'simulations', met: booking.simulations >= booking.needed, text: t('practice.readinessMap.booking.simulations', { done: booking.simulations, needed: booking.needed }) },
  ];

  return (
    <Card>
      <Text weight="bold">{t('practice.readinessMap.booking.notYetTitle')}</Text>
      {checks.map((c) => (
        <View key={c.key} accessible accessibilityLabel={`${c.text}: ${c.met ? t('practice.readinessMap.booking.met') : t('practice.readinessMap.booking.notMet')}`} style={styles.check}>
          <Icon icon={c.met ? CircleCheck : CircleDashed} color={c.met ? 'toneSuccessFg' : 'textMuted'} />
          <Text style={styles.flex}>{c.text}</Text>
        </View>
      ))}
    </Card>
  );
}

const styles = StyleSheet.create((theme) => ({
  rings: { flexDirection: 'row', gap: theme.space.sm },
  portion: { flex: 1, alignItems: 'center', gap: theme.space.xs },
  center: { textAlign: 'center' },
  check: { flexDirection: 'row', alignItems: 'center', gap: theme.space.sm, minHeight: theme.touch },
  flex: { flex: 1 },
}));
