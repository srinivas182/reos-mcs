import type { paths } from '@reos/api-client';
import { Languages, MessageCircleQuestion, Play, Sparkles, Wand2 } from 'lucide-react-native';
import { useState } from 'react';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { api } from '@/lib/api';
import { Button, Callout, Card, OptionGroup, Text, TextField } from '@/ui';
import { SaveFlashcard } from '@/features/practice/SaveFlashcard';

export type Answer = paths['/learn/lessons/{lesson}/ask']['post']['responses'][200]['content']['application/json'];
type Mode = 'ask' | 'simpler' | 'spanish' | 'quiz';

type Props = {
  lesson: number;
  /** Pauses the video and says where (seconds), or null with no video. */
  pause: () => number | null;
  /** Plays again, from a second when given. */
  resume: (seconds?: number) => void;
};

/** "4:05". */
function clock(seconds: number): string {
  return `${Math.floor(seconds / 60)}:${String(Math.floor(seconds % 60)).padStart(2, '0')}`;
}

/**
 * Interrupt and ask (candidate screen 9, Sprint 8 wrap-up): asking pauses
 * the lesson at the second; the answer comes from this course only, with
 * where it's from; then Explain simpler, En español, Quiz me, or Resume.
 */
export function AskInstructor({ lesson, pause, resume }: Props) {
  const { t } = useTranslation();
  const [question, setQuestion] = useState('');
  const [asked, setAsked] = useState('');
  const [answer, setAnswer] = useState<Answer | null>(null);
  const [busy, setBusy] = useState(false);
  const [failed, setFailed] = useState<string | null>(null);
  const [at, setAt] = useState<number | null>(null);
  const [choice, setChoice] = useState<string | null>(null);
  const [checked, setChecked] = useState(false);

  const run = async (mode: Mode, text: string) => {
    const second = pause();
    setAt(second);
    setBusy(true);
    setFailed(null);
    setChoice(null);
    setChecked(false);
    const { data, error } = await api().POST('/learn/lessons/{lesson}/ask', {
      params: { path: { lesson } },
      body: { question: text, mode, previous: mode === 'ask' ? null : (answer?.answer ?? null), at: second },
    });
    setBusy(false);
    if (data) {
      setAnswer(data);
      setAsked(text);
    } else {
      setFailed((error as { message?: string } | undefined)?.message ?? t('common.tryAgain'));
    }
  };

  const done = (seconds?: number) => {
    setAnswer(null);
    setQuestion('');
    resume(seconds);
  };

  return (
    <Card>
      <TextField label={t('learn.ask.bar')} value={question} onChangeText={setQuestion} maxLength={500} returnKeyType="send" onSubmitEditing={() => question.trim().length >= 2 && void run('ask', question.trim())} />
      <Button label={busy ? t('learn.ask.asking') : t('learn.ask.submit')} icon={MessageCircleQuestion} loading={busy} disabled={question.trim().length < 2} onPress={() => void run('ask', question.trim())} />
      {failed ? <Callout tone="warning" title={failed} live /> : null}
      {answer ? (
        <View accessibilityLiveRegion="polite" style={styles.block}>
          {at !== null ? (
            <Text tone="muted" variant="caption">
              {t('learn.ask.paused', { time: clock(at) })}
            </Text>
          ) : null}
          <Text>{answer.answer}</Text>
          {answer.found ? <SaveFlashcard source="ask" front={asked} back={answer.answer} /> : null}
          {answer.engine === 'simulator' ? (
            <Text tone="muted" variant="caption">
              {t('learn.ask.demo')}
            </Text>
          ) : null}
          {answer.sources.map((source) => (
            <View key={`${source.lesson_id}-${source.seconds ?? source.page ?? source.heading ?? ''}`} style={styles.row}>
              <Text tone="muted" variant="caption" style={styles.flex}>
                {t('learn.ask.from', { lesson: source.lesson })}
              </Text>
              {source.lesson_id === lesson && source.seconds !== null ? (
                <Button variant="ghost" icon={Play} label={t('learn.ask.play', { time: clock(source.seconds) })} onPress={() => done(source.seconds ?? undefined)} />
              ) : null}
            </View>
          ))}
          {answer.quiz ? (
            <>
              <OptionGroup
                label={answer.quiz.prompt}
                value={choice}
                onChange={setChoice}
                options={answer.quiz.options.map((option, i) => ({ value: String(i), label: option }))}
                correct={checked ? String(answer.quiz.correct) : undefined}
              />
              {checked ? (
                <Text>{`${choice === String(answer.quiz.correct) ? t('learn.ask.right') : t('learn.ask.wrong')} ${answer.quiz.explanation}`}</Text>
              ) : (
                <Button variant="secondary" label={t('learn.ask.check')} disabled={choice === null} onPress={() => setChecked(true)} />
              )}
            </>
          ) : null}
          <View style={styles.row}>
            {answer.found ? (
              <>
                <Button variant="secondary" icon={Wand2} label={t('learn.ask.simpler')} onPress={() => void run('simpler', asked)} />
                <Button variant="secondary" icon={Languages} label={t('learn.ask.spanish')} onPress={() => void run('spanish', asked)} />
                <Button variant="secondary" icon={Sparkles} label={t('learn.ask.quiz')} onPress={() => void run('quiz', asked)} />
              </>
            ) : null}
            <Button icon={Play} label={t('learn.ask.resume')} onPress={() => done()} />
          </View>
        </View>
      ) : null}
    </Card>
  );
}

const styles = StyleSheet.create((theme) => ({
  block: { gap: theme.space.sm },
  row: { flexDirection: 'row', flexWrap: 'wrap', gap: theme.space.xs, alignItems: 'center' },
  flex: { flex: 1 },
}));
