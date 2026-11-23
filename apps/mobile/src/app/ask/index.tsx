import { router, useLocalSearchParams } from 'expo-router';
import { BookOpen, Calculator, CalendarPlus, GraduationCap, Info, Languages, Lightbulb, ListChecks, MessageSquare, Mic, PlayCircle, SendHorizontal, Sparkles, Square } from 'lucide-react-native';
import { useEffect, useState } from 'react';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { SaveFlashcard } from '@/features/practice/SaveFlashcard';
import { ask, askInstructor, type AskMessage, type AskMode, type AskPackage, type AskThread, type AskThreadRow, clock, loadPackage, loadThread, loadThreads } from '@/lib/ask';
import { speechLanguage, useSpeech } from '@/lib/voice';
import { Button, Callout, Chip, ChatBubble, EmptyState, Icon, IconButton, ListRow, Screen, SectionTitle, SuggestionPills, Text, TextField, TitleBar } from '@/ui';

/**
 * Ask, the study mate (candidate screen 13, f02, k11; A10.1). Typed or
 * spoken questions; answers from the person's courses first, with the
 * lesson and second to watch; beyond them only when a sponsor pays; never
 * a credit balance for a candidate. Earlier conversations are listed under
 * the composer. `thread` reopens one; `q` asks at once (the Ask bar).
 */
export default function AskScreen() {
  const { t, i18n } = useTranslation();
  const params = useLocalSearchParams<{ thread?: string; q?: string }>();
  const [thread, setThread] = useState<AskThread | null>(null);
  const [threads, setThreads] = useState<AskThreadRow[]>([]);
  const [pack, setPack] = useState<AskPackage | null>(null);
  const [question, setQuestion] = useState('');
  const [busy, setBusy] = useState(false);
  const [problem, setProblem] = useState<string | null>(null);

  const send = async (mode: AskMode, text: string | null = null) => {
    setBusy(true);
    setProblem(null);
    try {
      const next = await ask(mode, text, thread?.uuid ?? null);
      setThread(next);
      setQuestion('');
      void loadThreads().then(setThreads);
    } catch (e) {
      setProblem(e instanceof Error ? e.message : String(e));
    } finally {
      setBusy(false);
    }
  };
  const speech = useSpeech(speechLanguage(i18n.language), (words) => {
    if (words.length >= 2) {
      void send('ask', words);
    }
  });

  useEffect(() => {
    void loadThreads().then(setThreads);
    void loadPackage().then(setPack);
    // Opened once, with what the link asked for (after the first paint).
    const timer = setTimeout(() => {
      if (params.thread) {
        void loadThread(params.thread).then(setThread);
      } else if (params.q && params.q.trim().length >= 2) {
        void send('ask', params.q.trim());
      }
    }, 0);

    return () => clearTimeout(timer);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const messages = thread?.messages ?? [];
  const lastAnswer = [...messages].reverse().find((m) => m.role === 'answer');
  const purpose = pack?.candidate ? t('ask.purposeCandidate') : t('ask.purpose');
  // Screen k10: free monthly credits for questions beyond the course, then the sponsor, then credits bought on reos.com.
  const credits =
    pack?.candidate && pack.free_monthly !== null
      ? [t('ask.beyondLeft', { left: pack.free_left ?? 0, monthly: pack.free_monthly }), pack.sponsor ? t('ask.sponsorNext', { sponsor: pack.sponsor }) : null, pack.credits_left > 0 ? t('ask.ownLeft', { credits: pack.credits_left }) : null]
          .filter(Boolean)
          .join(' ')
      : null;

  return (
    <Screen
      footer={
        <View style={styles.composer}>
          <View style={styles.flex}>
            <TextField label={t('ask.label')} value={speech.state === 'listening' ? speech.transcript : question} onChangeText={setQuestion} placeholder={t('ask.placeholder')} maxLength={500} returnKeyType="send" onSubmitEditing={() => question.trim().length >= 2 && void send('ask', question.trim())} />
          </View>
          <IconButton icon={speech.state === 'listening' ? Square : Mic} label={speech.state === 'listening' ? t('ask.stop') : t('ask.voice')} onPress={() => (speech.state === 'listening' ? speech.stop() : void speech.start())} />
          <IconButton icon={SendHorizontal} primary label={t('ask.submit')} onPress={() => question.trim().length >= 2 && !busy && void send('ask', question.trim())} />
        </View>
      }
    >
      <TitleBar title={t('ask.title')} onBack={() => router.back()} />
      <Text tone="muted">{purpose}</Text>
      {credits ? <Text variant="caption" tone="muted">{credits}</Text> : null}
      {speech.state === 'denied' ? <Callout tone="warning" title={t('ask.micDenied')} /> : null}

      {messages.length === 0 ? (
        <>
          <EmptyState icon={<Icon icon={Sparkles} color="action" size={32} />} title={t('ask.emptyTitle')} description={t('ask.empty')} />
          <SuggestionPills
            label={t('ask.suggestions')}
            suggestions={[
              { label: t('ask.suggest.term'), onPress: () => void send('ask', t('ask.suggest.termQ')) },
              { label: t('ask.suggest.math'), onPress: () => void send('ask', t('ask.suggest.mathQ')) },
            ]}
          />
        </>
      ) : (
        messages.map((m, i) =>
          m.role === 'instructor' ? (
            <View key={m.id} style={styles.answer}>
              <View style={styles.row}>
                <Icon icon={GraduationCap} color="action" size={18} />
                <Text variant="caption" weight="semibold" tone="muted">
                  {t('ask.instructor.from', { name: m.sponsor ?? '' })}
                </Text>
              </View>
              <ChatBubble from="person" senderName={m.sponsor ?? undefined}>
                {m.body}
              </ChatBubble>
            </View>
          ) : m.role === 'question' ? (
            <ChatBubble key={m.id} from="me">
              {m.body}
            </ChatBubble>
          ) : (
            <Answer key={m.id} answer={m} question={[...messages.slice(0, i)].reverse().find((q) => q.role === 'question')?.body ?? ''} last={m.id === lastAnswer?.id} busy={busy} onFollow={(mode) => void send(mode)} />
          ),
        )
      )}
      {thread && lastAnswer && thread.instructor === null ? (
        <Button
          variant="secondary"
          icon={GraduationCap}
          label={t('ask.instructor.ask')}
          disabled={busy}
          onPress={() =>
            void askInstructor(thread.uuid)
              .then(() => setThread({ ...thread, instructor: 'waiting' }))
              .catch((e: unknown) => setProblem(e instanceof Error ? e.message : String(e)))
          }
        />
      ) : thread?.instructor === 'waiting' ? (
        <Callout tone="info" title={t('ask.instructor.sent')} />
      ) : null}
      {busy ? <Text tone="muted">{t('ask.asking')}</Text> : null}
      {problem ? <Callout tone="danger" title={problem} live /> : null}

      {threads.length > 0 ? <SectionTitle>{t('ask.threads')}</SectionTitle> : null}
      {threads
        .filter((row) => row.uuid !== thread?.uuid)
        .slice(0, 10)
        .map((row) => (
          <ListRow key={row.uuid} icon={MessageSquare} title={row.title} onPress={() => void loadThread(row.uuid).then(setThread)} />
        ))}
    </Screen>
  );
}

function Answer({ answer, question, last, busy, onFollow }: { answer: AskMessage; question: string; last: boolean; busy: boolean; onFollow: (mode: AskMode) => void }) {
  const { t } = useTranslation();
  const [picked, setPicked] = useState<number | null>(null);
  const source = answer.sources[0];
  const label = answer.tier === 'content' ? t('ask.fromCourseShort') : answer.tier === 'sponsored' ? (answer.sponsor ? t('ask.beyondSponsored', { sponsor: answer.sponsor }) : t('ask.beyond')) : t('ask.notCovered');

  return (
    <View style={styles.answer}>
      <View style={styles.row}>
        <Icon icon={answer.tier === 'content' ? BookOpen : answer.tier === 'sponsored' ? Sparkles : Info} color={answer.tier === 'limited' ? 'warning' : 'action'} size={18} />
        <Text variant="caption" weight="semibold" tone="muted">
          {label}
        </Text>
      </View>
      <ChatBubble from="assistant" source={source ? source.lesson : undefined}>
        {answer.body}
      </ChatBubble>
      {source ? (
        <Chip
          tone="info"
          label={source.seconds !== null ? t('ask.watchAt', { time: clock(source.seconds) }) : t('ask.openLesson')}
          icon={PlayCircle}
          onPress={() => router.push({ pathname: '/learn/[id]', params: { id: String(source.lesson_id), ...(source.seconds !== null ? { t: String(source.seconds) } : source.page !== null ? { page: String(source.page) } : {}) } })}
        />
      ) : null}
      {answer.quiz ? (
        <View style={styles.quiz}>
          <Text weight="semibold">{answer.quiz.prompt}</Text>
          {answer.quiz.options.map((option, i) => (
            <Button key={option} variant={picked === null ? 'secondary' : i === answer.quiz?.correct ? 'primary' : 'secondary'} label={option} fullWidth onPress={() => setPicked(i)} />
          ))}
          {picked !== null ? <Text>{`${picked === answer.quiz.correct ? t('learn.ask.right') : t('learn.ask.wrong')} ${answer.quiz.explanation}`}</Text> : null}
        </View>
      ) : null}
      <View style={styles.wrap}>
        {last && answer.tier !== 'limited' ? (
          <>
            <Chip label={t('learn.ask.quiz')} icon={ListChecks} onPress={busy ? undefined : () => onFollow('quiz')} />
            <Chip label={t('learn.ask.simpler')} icon={Lightbulb} onPress={busy ? undefined : () => onFollow('simpler')} />
            <Chip label={t('learn.ask.spanish')} icon={Languages} onPress={busy ? undefined : () => onFollow('spanish')} />
          </>
        ) : null}
        {answer.tier === 'limited' ? <Chip tone="info" label={t('ask.bookInstructor')} icon={CalendarPlus} onPress={() => router.push('/bookings')} /> : null}
        {answer.math_kind ? <Chip tone="info" label={t('ask.similar')} icon={Calculator} onPress={() => router.push({ pathname: '/practice/math', params: { kind: answer.math_kind ?? '' } })} /> : null}
      </View>
      {answer.tier !== 'limited' ? <SaveFlashcard source="ask" front={question} back={answer.body} /> : null}
    </View>
  );
}

const styles = StyleSheet.create((theme) => ({
  composer: { flexDirection: 'row', alignItems: 'flex-end', gap: theme.space.sm, paddingHorizontal: theme.m.gutter, paddingBottom: theme.space.lg },
  flex: { flex: 1 },
  answer: { gap: theme.space.sm },
  row: { flexDirection: 'row', alignItems: 'center', gap: theme.space.xs },
  wrap: { flexDirection: 'row', flexWrap: 'wrap', gap: theme.space.sm },
  quiz: { gap: theme.space.sm },
}));
