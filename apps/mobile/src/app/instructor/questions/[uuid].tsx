import { router, useLocalSearchParams } from 'expo-router';
import { Mic, Square } from 'lucide-react-native';
import { useEffect, useState } from 'react';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { answerStudent, loadStudentQuestion, type StudentQuestion } from '@/lib/ask';
import { speechLanguage, useSpeech } from '@/lib/voice';
import { Button, Callout, Card, IconButton, Screen, SwitchRow, Text, TextField, TitleBar, useUndoToast } from '@/ui';

/**
 * Answer a student (instructor screen i10, A10.1b): the question and what
 * the course content said; the answer, typed or dictated; Add to course
 * knowledge (on: answer once, help every future student) and Make a
 * flashcard.
 */
export default function AnswerStudentScreen() {
  const { t, i18n } = useTranslation();
  const toast = useUndoToast();
  const { uuid } = useLocalSearchParams<{ uuid: string }>();
  const [question, setQuestion] = useState<StudentQuestion | null>(null);
  const [answer, setAnswer] = useState('');
  const [knowledge, setKnowledge] = useState(true);
  const [flashcard, setFlashcard] = useState(false);
  const [busy, setBusy] = useState(false);
  const [problem, setProblem] = useState<string | null>(null);
  const speech = useSpeech(speechLanguage(i18n.language), (words) => words !== '' && setAnswer((now) => (now === '' ? words : `${now} ${words}`)));

  useEffect(() => {
    void loadStudentQuestion(String(uuid)).then(setQuestion);
  }, [uuid]);

  const send = async () => {
    if (!question) {
      return;
    }
    setBusy(true);
    setProblem(null);
    try {
      await answerStudent(question.uuid, { answer: answer.trim(), add_to_knowledge: knowledge, flashcard });
      toast({ message: t('ask.instructor.sentToStudent', { name: question.student }) });
      router.back();
    } catch (e) {
      setProblem(e instanceof Error ? e.message : String(e));
    } finally {
      setBusy(false);
    }
  };

  return (
    <Screen
      footer={
        question?.status === 'waiting' ? (
          <View style={styles.footer}>
            <Button label={t('ask.instructor.send')} loading={busy} disabled={answer.trim().length < 2} fullWidth onPress={() => void send()} />
          </View>
        ) : undefined
      }
    >
      <TitleBar title={t('ask.instructor.title')} onBack={() => router.back()} />
      {question ? (
        <>
          <Text tone="muted">{t('ask.instructor.askedBy', { student: question.student, course: question.course })}</Text>
          <Text variant="lead" weight="semibold">
            {question.question}
          </Text>
          {question.content_answer ? (
            <Card>
              <Text variant="caption" weight="semibold" tone="muted">
                {t('ask.instructor.contentSaid')}
              </Text>
              <Text>{question.content_answer}</Text>
            </Card>
          ) : null}
          {question.status === 'answered' ? (
            <Card>
              <Text weight="semibold">{question.answered_by ?? ''}</Text>
              <Text>{question.answer ?? ''}</Text>
              {question.added_to_knowledge ? <Callout tone="success" title={t('ask.instructor.addedToKnowledge')} /> : null}
            </Card>
          ) : (
            <>
              <View style={styles.row}>
                <View style={styles.flex}>
                  <TextField label={t('ask.instructor.yourAnswer')} value={answer} onChangeText={setAnswer} multiline maxLength={4000} />
                </View>
                <IconButton icon={speech.state === 'listening' ? Square : Mic} label={speech.state === 'listening' ? t('ask.stop') : t('ask.voice')} onPress={() => (speech.state === 'listening' ? speech.stop() : void speech.start())} />
              </View>
              <SwitchRow label={t('ask.instructor.addToKnowledge')} value={knowledge} onValueChange={setKnowledge} />
              <SwitchRow label={t('ask.instructor.flashcard')} value={flashcard} onValueChange={setFlashcard} />
            </>
          )}
          {problem ? <Callout tone="danger" title={problem} live /> : null}
        </>
      ) : null}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  footer: { paddingHorizontal: theme.m.gutter, paddingBottom: theme.space.xl },
  row: { flexDirection: 'row', alignItems: 'flex-end', gap: theme.space.sm },
  flex: { flex: 1 },
}));
