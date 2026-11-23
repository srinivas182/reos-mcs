import { router, useFocusEffect } from 'expo-router';
import { ClipboardCheck, Gauge, GraduationCap, Target } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { CoachCard } from '@/features/practice/CoachCard';
import { asTopic, type Coach, type ExamKind, type HubTest, isExam, loadCoach, loadHub, startTest } from '@/lib/practice';
import { Button, Card, ListRow, OptionGroup, Screen, SectionTitle, Sheet, Text, TitleBar } from '@/ui';

/**
 * The practice hub (candidate screens 38 and 39): the smart drill and every
 * mode ranked by what helps most (A9.2b), the readiness map, the Texas
 * diagnostic, the portion tests and full simulation (A9.1), and a quiz per
 * topic, each with its best score.
 */
export default function PracticeHub() {
  const { t } = useTranslation();
  const [tests, setTests] = useState<HubTest[] | null>(null);
  const [coach, setCoach] = useState<Coach | null>(null);
  const [busy, setBusy] = useState(false);

  useFocusEffect(
    useCallback(() => {
      void loadHub()
        .then(setTests)
        .catch(() => setTests([]));
      void loadCoach()
        .then(setCoach)
        .catch(() => setCoach(null));
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
  const exams = (tests ?? []).flatMap((x) => (isExam(x.kind) ? [{ test: x, kind: x.kind as ExamKind }] : []));
  const [intro, setIntro] = useState<{ test: HubTest; kind: ExamKind } | null>(null);
  const [mode, setMode] = useState<'exam' | 'practice'>('exam');

  const startExam = async () => {
    if (!intro) return;
    setBusy(true);
    const attempt = await startTest(intro.kind, null, mode).catch(() => null);
    setBusy(false);
    setIntro(null);
    if (attempt) router.push({ pathname: '/practice/[id]', params: { id: String(attempt.id) } });
  };

  return (
    <Screen>
      <TitleBar title={t('practice.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('practice.description')}</Text>
      {coach ? (
        <CoachCard
          coach={coach}
          onSimulation={() => {
            const simulation = exams.find((x) => x.kind === 'simulation');
            if (simulation) setIntro(simulation);
          }}
        />
      ) : null}
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
      <ListRow icon={Gauge} tone="success" title={t('practice.readinessMap.title')} subtitle={t('practice.readinessMap.description')} onPress={() => router.push('/practice/readiness')} />
      {exams.length > 0 ? <SectionTitle>{t('practice.portions.title')}</SectionTitle> : null}
      {exams.map(({ test, kind }) => (
        <ListRow
          key={kind}
          icon={GraduationCap}
          tone="info"
          title={t(`practice.portions.${kind}.title`)}
          subtitle={[t('practice.intro.format', { questions: test.questions, minutes: test.minutes ?? 0 }), test.best !== null ? t('practice.best', { best: test.best }) : null].filter(Boolean).join(' · ')}
          onPress={() => (test.open !== null ? void begin(test) : setIntro({ test, kind }))}
        />
      ))}
      <Sheet open={intro !== null} onClose={() => setIntro(null)} title={intro ? t(`practice.portions.${intro.kind}.title`) : ''}>
        {intro ? (
          <>
            <Text>{t('practice.intro.format', { questions: intro.test.questions, minutes: intro.test.minutes ?? 0 })}</Text>
            <Text tone="muted">{t(`practice.intro.pass.${intro.kind === 'simulation' ? 'both' : intro.kind}`)}</Text>
            {intro.kind === 'simulation' ? <Text tone="muted">{t('practice.intro.portions')}</Text> : null}
            <Text tone="muted">{t('practice.intro.tools')}</Text>
            <Text tone="muted">{t('practice.intro.pretest')}</Text>
            <OptionGroup<'exam' | 'practice'>
              label={t('practice.intro.modeLabel')}
              value={mode}
              onChange={setMode}
              options={[
                { value: 'exam', label: t('practice.intro.exam'), description: t('practice.intro.examHint') },
                { value: 'practice', label: t('practice.intro.practice'), description: t('practice.intro.practiceHint') },
              ]}
            />
            <Button label={t('practice.intro.start')} loading={busy} onPress={() => void startExam()} fullWidth />
          </>
        ) : null}
      </Sheet>
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
