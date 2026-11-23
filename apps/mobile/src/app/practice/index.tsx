import { router, useFocusEffect } from 'expo-router';
import { ClipboardCheck, Target } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { asTopic, type HubTest, loadHub, startTest } from '@/lib/practice';
import { Button, Card, ListRow, Screen, SectionTitle, Text, TitleBar } from '@/ui';

/**
 * Practice tests (candidate screen 39, A8.3): the Texas diagnostic and a quiz
 * per topic, each with its best score. Portion tests and the full
 * simulation join in A9.1.
 */
export default function PracticeHub() {
  const { t } = useTranslation();
  const [tests, setTests] = useState<HubTest[] | null>(null);
  const [busy, setBusy] = useState(false);

  useFocusEffect(
    useCallback(() => {
      void loadHub()
        .then(setTests)
        .catch(() => setTests([]));
    }, []),
  );

  const begin = async (test: HubTest) => {
    if (test.open !== null) {
      router.push({ pathname: '/practice/[id]', params: { id: String(test.open) } });

      return;
    }
    setBusy(true);
    const attempt = await startTest(test.kind, asTopic(test.topic)).catch(() => null);
    setBusy(false);
    if (attempt) router.push({ pathname: '/practice/[id]', params: { id: String(attempt.id) } });
  };

  const diagnostic = tests?.find((x) => x.kind === 'diagnostic');

  return (
    <Screen>
      <TitleBar title={t('practice.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('practice.description')}</Text>
      {diagnostic ? (
        <Card>
          <Text variant="bodyLarge" weight="semibold">
            {t('practice.diagnostic.title')}
          </Text>
          <Text tone="muted">{t('practice.diagnostic.description', { questions: diagnostic.questions, minutes: diagnostic.minutes })}</Text>
          {diagnostic.best !== null ? <Text>{t('practice.scores', { best: diagnostic.best, last: diagnostic.last })}</Text> : null}
          <Button
            icon={ClipboardCheck}
            loading={busy}
            label={diagnostic.open !== null ? t('practice.carryOn') : diagnostic.best !== null ? t('practice.diagnostic.again') : t('practice.diagnostic.start')}
            onPress={() => void begin(diagnostic)}
            fullWidth
          />
          {diagnostic.last_attempt !== null ? (
            <Button variant="secondary" label={t('practice.seeResults')} onPress={() => router.push({ pathname: '/practice/results/[id]', params: { id: String(diagnostic.last_attempt) } })} />
          ) : null}
        </Card>
      ) : null}
      <SectionTitle>{t('practice.topicQuizzes')}</SectionTitle>
      {(tests ?? [])
        .filter((x) => x.kind === 'topic')
        .map((test) => {
          const topic = asTopic(test.topic);
          const name = topic ? t(`practice.topics.${topic}`) : '';

          return (
            <ListRow
              key={test.topic}
              icon={Target}
              tone="info"
              title={name}
              subtitle={[t('practice.questions', { count: test.questions }), test.best !== null ? t('practice.best', { best: test.best }) : null].filter(Boolean).join(' · ')}
              onPress={() => void begin(test)}
            />
          );
        })}
    </Screen>
  );
}
