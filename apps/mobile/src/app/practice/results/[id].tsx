import { router, useLocalSearchParams } from 'expo-router';
import { useEffect, useState } from 'react';
import { ActivityIndicator, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';

import { asTopic, countdown, loadResults, type Results } from '@/lib/practice';
import { Button, Callout, Card, ProgressBar, Screen, SectionTitle, Text, TitleBar } from '@/ui';

/**
 * Your results (candidate screen 43, A8.3): score and pass line, the change
 * since last time, topics weakest first, and every answer with why and its
 * source. Readiness and the study plan have already followed from it.
 */
export default function ResultsScreen() {
  const { t } = useTranslation();
  const { theme } = useUnistyles();
  const { id } = useLocalSearchParams<{ id: string }>();
  const [results, setResults] = useState<Results | null | undefined>(undefined);

  useEffect(() => {
    let active = true;
    void loadResults(Number(id))
      .catch(() => null)
      .then((found) => active && setResults(found));

    return () => {
      active = false;
    };
  }, [id]);

  if (results === undefined) {
    return (
      <Screen>
        <TitleBar title={t('practice.results.title')} onBack={() => router.back()} />
        <ActivityIndicator color={theme.colors.action} />
      </Screen>
    );
  }

  if (results === null) {
    return (
      <Screen>
        <TitleBar title={t('practice.results.title')} onBack={() => router.back()} />
        <Callout tone="danger" title={t('practice.errors.stillOpen')} />
      </Screen>
    );
  }

  const passed = results.score >= results.pass_line;
  const change = results.previous === null ? null : results.score - results.previous;
  const name = (topic: string) => {
    const known = asTopic(topic);

    return known ? t(`practice.topics.${known}`) : topic;
  };

  return (
    <Screen>
      <TitleBar title={t('practice.results.title')} onBack={() => router.replace('/')} />
      <Text tone="muted">{results.kind === 'diagnostic' ? t('practice.results.diagnosticPurpose') : t('practice.results.purpose')}</Text>
      <View style={styles.tiles}>
        {[
          [t('practice.results.score'), `${results.score}%`],
          [t('practice.results.right'), `${results.correct}/${results.total}`],
          [t('practice.results.time'), countdown(results.seconds)],
        ].map(([label, value]) => (
          <Card key={label} style={styles.tile}>
            <Text variant="title">{value}</Text>
            <Text variant="caption" tone="muted">
              {label}
            </Text>
          </Card>
        ))}
      </View>
      <Callout
        tone={passed ? 'success' : 'warning'}
        title={passed ? t('practice.results.above', { line: results.pass_line }) : t('practice.results.below', { line: results.pass_line })}
        body={change !== null ? t('practice.results.change', { change: change > 0 ? `+${change}` : change }) : undefined}
      />
      <SectionTitle>{t('practice.results.byTopic')}</SectionTitle>
      <Card>
        {results.topics.map((topic) => (
          <View key={topic.topic} style={styles.topic}>
            <Text weight="semibold">{`${name(topic.topic)} · ${topic.percent}%`}</Text>
            <ProgressBar value={topic.percent} label={name(topic.topic)} color={topic.percent >= results.pass_line ? 'success' : 'warning'} />
          </View>
        ))}
      </Card>
      {results.weakest.length > 0 ? <Text>{t('practice.results.next', { topics: results.weakest.map(name).join(', ') })}</Text> : null}
      <Button label={results.weakest.length > 0 ? t('practice.results.fixWeakest') : t('practice.results.seePlan')} onPress={() => router.replace('/')} fullWidth />
      <SectionTitle>{t('practice.results.review')}</SectionTitle>
      {results.review.map((q) => (
        <Card key={q.id}>
          <Text weight="semibold">{`${q.n}. ${q.prompt}`}</Text>
          <Text tone={q.correct ? 'success' : 'danger'}>
            {q.choice === null ? t('practice.results.blank') : t('practice.results.yours', { answer: q.options[q.choice] })}
          </Text>
          {q.correct ? null : <Text>{t('practice.results.rightAnswer', { answer: q.options[q.right] })}</Text>}
          <Text tone="muted">{q.explanation}</Text>
          <Text variant="caption" tone="muted">
            {t('player.checkin.source', { source: q.source })}
          </Text>
        </Card>
      ))}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  tiles: { flexDirection: 'row', flexWrap: 'wrap', gap: theme.space.sm },
  topic: { gap: theme.space.xs },
  tile: { flexGrow: 1, minWidth: 96 },
}));
