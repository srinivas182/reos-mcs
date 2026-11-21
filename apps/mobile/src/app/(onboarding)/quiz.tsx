import type { Schemas } from '@reos/api-client';
import { router } from 'expo-router';
import { useEffect, useState } from 'react';
import { ActivityIndicator, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';

import { api } from '@/lib/api';
import { updateDraft, useDraft } from '@/lib/onboarding';
import { Button, Callout, Chip, Eyebrow, OptionGroup, ProgressBar, Screen, Text } from '@/ui';

type Question = Schemas['QuizQuestionResource'];

/**
 * Free quiz, no account (mobile journey screen 6): ten exam-style
 * questions, one per screen, each explained the moment it's answered.
 * Answers stay on the phone until sign-up.
 */
export default function QuizScreen() {
  const { t, i18n } = useTranslation();
  const { theme } = useUnistyles();
  const { state } = useDraft();
  const [questions, setQuestions] = useState<Question[] | null>(null);
  const [failed, setFailed] = useState(false);
  const [index, setIndex] = useState(0);
  const [answers, setAnswers] = useState<{ question_id: number; choice: number }[]>([]);
  const [attempt, setAttempt] = useState(0);

  useEffect(() => {
    let active = true;
    void api()
      .GET('/states/{state}/free-quiz', { params: { path: { state: state ?? 'TX' } }, headers: { 'Accept-Language': i18n.language } })
      .then(({ data }) => {
        if (!active) {
          return;
        }
        if (data) {
          setQuestions(data.data);
        } else {
          setFailed(true);
        }
      })
      .catch(() => active && setFailed(true));

    return () => {
      active = false;
    };
  }, [state, i18n.language, attempt]);

  if (failed) {
    return (
      <Screen>
        <Callout tone="danger" title={t('onboarding.quiz.loadFailed')} action={<Button label={t('common.tryAgain')} variant="secondary" onPress={() => {
              setFailed(false);
              setAttempt((n) => n + 1);
            }} />} />
      </Screen>
    );
  }

  if (questions === null || questions.length === 0) {
    return (
      <Screen>
        <ActivityIndicator color={theme.colors.action} />
      </Screen>
    );
  }

  const question = questions[index];
  if (!question) {
    return null;
  }

  const answer = answers[index];
  const answered = answer !== undefined;
  const right = answered && answer.choice === question.correct_option;
  const last = index === questions.length - 1;

  const choose = (value: string) => {
    if (answered) {
      return;
    }
    setAnswers([...answers, { question_id: question.id, choice: Number(value) }]);
  };

  const next = () => {
    if (last) {
      updateDraft({
        answers,
        results: answers.map((a) => {
          const q = questions.find((item) => item.id === a.question_id);

          return { topic: q?.topic ?? question.topic, correct: q !== undefined && a.choice === q.correct_option };
        }),
      });
      router.replace('/score');
    } else {
      setIndex(index + 1);
    }
  };

  return (
    <Screen
      footer={
        <View style={styles.footer}>
          <Button label={last ? t('onboarding.quiz.finish') : t('onboarding.quiz.next')} disabled={!answered} fullWidth onPress={next} />
        </View>
      }
    >
      <View style={styles.top}>
        <Text tone="muted" weight="semibold" style={styles.flex}>
          {t('onboarding.quiz.header')}
        </Text>
        <Chip label={t('onboarding.quiz.count', { number: index + 1, total: questions.length })} />
      </View>
      <ProgressBar value={(100 * (index + (answered ? 1 : 0))) / questions.length} label={t('onboarding.quiz.progress')} />
      <Eyebrow>{t(`onboarding.topics.${question.topic}`)}</Eyebrow>
      <Text variant="title" heading>
        {question.question}
      </Text>
      <OptionGroup
        label={t('onboarding.quiz.answers')}
        lettered
        options={question.options.map((label, i) => ({ value: String(i), label }))}
        value={answered ? String(answer.choice) : null}
        onChange={choose}
        correct={answered ? String(question.correct_option) : undefined}
      />
      {answered ? (
        <Callout
          tone={right ? 'success' : 'danger'}
          live
          title={right ? t('onboarding.quiz.correct') : t('onboarding.quiz.incorrect')}
          body={`${question.explanation}\n${t('onboarding.quiz.source', { source: question.source })}`}
        />
      ) : null}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  footer: { paddingHorizontal: theme.m.gutter, paddingBottom: theme.space.xl },
  top: { flexDirection: 'row', alignItems: 'center', gap: theme.space.md },
  flex: { flex: 1 },
}));
