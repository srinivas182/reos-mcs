import { router, useLocalSearchParams } from 'expo-router';
import { ArrowLeft, ArrowRight, Flag } from 'lucide-react-native';
import { useEffect, useRef, useState } from 'react';
import { ActivityIndicator, Pressable, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';

import { type Attempt, asTopic, countdown, loadAttempt, saveAnswer, submitTest, type TestQuestion } from '@/lib/practice';
import { Button, Callout, Chip, OptionGroup, Screen, Sheet, Text, TitleBar } from '@/ui';

/**
 * A test (candidate screens 40–42, A8.3): the counter, the countdown on the
 * server's clock, the topic, four options, Back, Flag and Next; the
 * navigator shows answered, flagged and blank, and Review before submit
 * warns once about blanks. Every tap is saved; when time runs out the test
 * submits itself. The diagnostic gives results at the end; a topic quiz
 * shows why after each answer.
 */
export default function TestScreen() {
  const { t } = useTranslation();
  const { theme } = useUnistyles();
  const { id } = useLocalSearchParams<{ id: string }>();
  const attemptId = Number(id);
  const [attempt, setAttempt] = useState<Attempt | null | undefined>(undefined);
  const [questions, setQuestions] = useState<TestQuestion[]>([]);
  const [at, setAt] = useState(0);
  const [left, setLeft] = useState<number | null>(null);
  const [reviewing, setReviewing] = useState(false);
  const [failed, setFailed] = useState(false);
  const submitted = useRef(false);

  useEffect(() => {
    let active = true;
    void loadAttempt(attemptId)
      .catch(() => null)
      .then((found) => {
        if (!active) return;
        if (found?.submitted) {
          router.replace({ pathname: '/practice/results/[id]', params: { id: String(found.id) } });

          return;
        }
        setAttempt(found);
        setQuestions(found?.questions ?? []);
        setAt(Math.max(0, (found?.questions ?? []).findIndex((q) => q.choice === null)));
        setLeft(found?.seconds_left ?? null);
      });

    return () => {
      active = false;
    };
  }, [attemptId]);

  const timed = left !== null;
  useEffect(() => {
    if (!timed) return;
    const timer = setInterval(() => setLeft((s) => (s === null ? s : Math.max(0, s - 1))), 1000);

    return () => clearInterval(timer);
  }, [timed]);

  const finish = async () => {
    if (submitted.current) return;
    submitted.current = true;
    setReviewing(false);
    await submitTest(attemptId).catch(() => null);
    router.replace({ pathname: '/practice/results/[id]', params: { id: String(attemptId) } });
  };

  useEffect(() => {
    if (left === 0) void finish();
  }, [left]); // eslint-disable-line react-hooks/exhaustive-deps

  if (attempt === undefined) {
    return (
      <Screen>
        <TitleBar title={t('practice.title')} onBack={() => router.back()} />
        <ActivityIndicator color={theme.colors.action} />
      </Screen>
    );
  }

  const question = questions[at];
  if (!attempt || !question) {
    return (
      <Screen>
        <TitleBar title={t('practice.title')} onBack={() => router.back()} />
        <Callout tone="danger" title={t('practice.errors.ended')} />
      </Screen>
    );
  }

  const topic = asTopic(question.topic);
  const blanks = questions.filter((q) => q.choice === null).length;
  const save = async (change: { choice?: number; flagged?: boolean }) => {
    const next = { choice: change.choice ?? question.choice, flagged: change.flagged ?? question.flagged };
    setQuestions((list) => list.map((q) => (q.id === question.id ? { ...q, ...next } : q)));
    const saved = await saveAnswer(attempt.id, question.id, next.choice, next.flagged).catch(() => null);
    setFailed(saved === null);
    if (saved) setQuestions((list) => list.map((q) => (q.id === saved.id ? saved : q)));
  };
  const title = attempt.kind === 'diagnostic' ? t('practice.diagnostic.title') : t('practice.topicQuiz', { topic: topic ? t(`practice.topics.${topic}`) : '' });

  return (
    <Screen>
      <TitleBar title={title} onBack={() => router.back()} action={left !== null ? <Chip tone={left < 300 ? 'warning' : 'neutral'} label={countdown(left)} accessibilityLabel={t('practice.test.timeLeft', { time: countdown(left) })} /> : undefined} />
      <Pressable accessibilityRole="button" accessibilityLabel={t('practice.test.openMap', { n: question.n, total: attempt.total })} onPress={() => setReviewing(true)}>
        <Text tone="muted">{`${t('practice.test.counter', { n: question.n, total: attempt.total })} · ${topic ? t(`practice.topics.${topic}`) : ''}`}</Text>
      </Pressable>
      <Text variant="lead" weight="semibold">
        {question.prompt}
      </Text>
      <OptionGroup
        label={question.prompt}
        value={question.choice === null ? null : String(question.choice)}
        options={question.options.map((option, i) => ({ value: String(i), label: option }))}
        onChange={(value) => (question.feedback ? undefined : void save({ choice: Number(value) }))}
      />
      {question.feedback ? (
        <Callout
          tone={question.feedback.correct ? 'success' : 'warning'}
          title={question.feedback.correct ? t('player.checkin.right') : t('practice.test.wrong', { answer: question.options[question.feedback.right] })}
          body={`${question.feedback.explanation}\n${t('player.checkin.source', { source: question.feedback.source })}`}
          live
        />
      ) : null}
      {failed ? <Callout tone="danger" title={t('practice.errors.save')} live /> : null}
      <View style={styles.row}>
        <Button variant="secondary" icon={ArrowLeft} label={t('practice.test.previous')} disabled={at === 0} onPress={() => setAt(at - 1)} />
        <Button
          variant="secondary"
          icon={Flag}
          label={question.flagged ? t('practice.test.unflag') : t('practice.test.flag')}
          accessibilityState={{ selected: question.flagged }}
          onPress={() => void save({ flagged: !question.flagged })}
        />
      </View>
      {at < questions.length - 1 ? (
        <Button trailingIcon={ArrowRight} label={t('practice.test.next')} onPress={() => setAt(at + 1)} fullWidth />
      ) : (
        <Button label={t('practice.test.review')} onPress={() => setReviewing(true)} fullWidth />
      )}

      <Sheet open={reviewing} onClose={() => setReviewing(false)} title={t('practice.test.reviewTitle')}>
        <Text>{blanks > 0 ? t('practice.test.blanks', { count: blanks }) : t('practice.test.allAnswered')}</Text>
        <View style={styles.grid} accessibilityLabel={t('practice.test.map')}>
          {questions.map((q, i) => (
            <Pressable
              key={q.id}
              accessibilityRole="button"
              accessibilityLabel={t('practice.test.mapItem', {
                n: q.n,
                state: [q.choice !== null ? t('practice.test.answered') : t('practice.test.blank'), q.flagged ? t('practice.test.flagged') : null].filter(Boolean).join(', '),
              })}
              onPress={() => {
                setAt(i);
                setReviewing(false);
              }}
              style={[styles.cell, q.choice !== null && styles.answered, i === at && styles.current]}
            >
              <Text weight="semibold">{`${q.n}${q.flagged ? '⚑' : ''}`}</Text>
            </Pressable>
          ))}
        </View>
        {blanks > 0 ? (
          <Button
            variant="secondary"
            label={t('practice.test.firstBlank')}
            onPress={() => {
              setAt(questions.findIndex((q) => q.choice === null));
              setReviewing(false);
            }}
          />
        ) : null}
        <Button label={blanks > 0 ? t('practice.test.submitAnyway') : t('practice.test.submit')} onPress={() => void finish()} fullWidth />
      </Sheet>
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  row: { flexDirection: 'row', flexWrap: 'wrap', gap: theme.space.sm },
  grid: { flexDirection: 'row', flexWrap: 'wrap', gap: theme.space.xs },
  cell: { width: 48, height: 48, borderRadius: theme.radius.md, borderWidth: 1, borderColor: theme.colors.border, alignItems: 'center', justifyContent: 'center' },
  answered: { backgroundColor: theme.colors.actionTint, borderColor: theme.colors.action },
  current: { borderWidth: 3, borderColor: theme.colors.action },
}));
