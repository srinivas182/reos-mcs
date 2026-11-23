import { router } from 'expo-router';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { enterResult, type Portion, type Topic, TOPICS, useJourney } from '@/lib/licence';
import { Button, Callout, Card, PillGroup, Screen, SwitchRow, Text, TitleBar } from '@/ui';

type Answer = 'passed' | 'failed' | 'skipped';

/**
 * Enter your result (candidate screen 15 › result entry, A10.3a): passed or
 * didn't for each portion not yet passed, and, if one was missed, the weak
 * areas from the score report, used only for the retake plan.
 */
export default function ResultScreen() {
  const { t } = useTranslation();
  const { journey, problem, run } = useJourney();
  const [answers, setAnswers] = useState<Partial<Record<Portion, Answer>>>({});
  const [weak, setWeak] = useState<Topic[]>([]);

  if (!journey) {
    return <Screen>{null}</Screen>;
  }

  const open = (['national', 'state'] as const).filter((p) => journey.results[p].passed !== true);
  const answer = (p: Portion): Answer => answers[p] ?? (open.some((o) => o === p) ? 'passed' : 'skipped');
  const missed = open.some((p) => answer(p) === 'failed');
  const value = (a: Answer) => (a === 'skipped' ? null : a === 'passed');

  const save = async () => {
    if (await run(() => enterResult(value(answer('national')), value(answer('state')), missed ? weak : []))) {
      router.replace('/licence-journey');
    }
  };

  return (
    <Screen>
      <TitleBar title={t('licence.result.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('licence.result.purpose')}</Text>
      {problem ? <Callout tone="danger" title={problem} live /> : null}
      {!journey.results.can_enter ? (
        <Text tone="muted">{t('licence.result.notYet')}</Text>
      ) : (
        <>
          {open.map((portion) => (
            <Card key={portion}>
              <Text weight="semibold">{t(`licence.portions.${portion}`)}</Text>
              <PillGroup<Answer>
                label={t(`licence.portions.${portion}`)}
                value={answer(portion)}
                options={(['passed', 'failed', 'skipped'] as const).map((a) => ({ value: a, label: t(`licence.result.answers.${a}`) }))}
                onChange={(a) => setAnswers({ ...answers, [portion]: a })}
              />
            </Card>
          ))}
          {(['national', 'state'] as const)
            .filter((p) => journey.results[p].passed === true)
            .map((p) => (
              <Text key={p} tone="muted">
                {t('licence.result.kept', { portion: t(`licence.portions.${p}`) })}
              </Text>
            ))}
          {missed ? (
            <Card>
              <Text weight="semibold">{t('licence.result.weakTitle')}</Text>
              <Text variant="caption" tone="muted">
                {t('licence.result.weakHint')}
              </Text>
              {TOPICS.map((topic) => (
                <SwitchRow key={topic} label={t(`practice.topics.${topic}`)} value={weak.includes(topic)} onValueChange={(on) => setWeak(on ? [...weak, topic] : weak.filter((w) => w !== topic))} />
              ))}
            </Card>
          ) : null}
          <Button label={t('licence.result.save')} disabled={open.every((p) => answer(p) === 'skipped')} onPress={() => void save()} />
        </>
      )}
    </Screen>
  );
}
