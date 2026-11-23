import * as Speech from 'expo-speech';
import { ExpoSpeechRecognitionModule, useSpeechRecognitionEvent } from 'expo-speech-recognition';
import { router } from 'expo-router';
import { Headphones, Mic, MicOff, Repeat, SkipForward, Square } from 'lucide-react-native';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Pressable, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { type Remote, startRemote } from '@/features/handsFree/remote';
import { feedbackText, type Language, questionText, understand, voiceLanguage } from '@/lib/handsFree';
import { type Attempt, type Results, saveAnswer, startTest, submitTest } from '@/lib/practice';
import { Button, Callout, Card, Icon, Screen, Text, TitleBar } from '@/ui';

type Phase = 'intro' | 'speaking' | 'listening' | 'checking' | 'done';
type Question = Attempt['questions'][number];

const LETTERS = ['A', 'B', 'C', 'D'];

/**
 * Hands-free mode (candidate screen 51, A9.3): a smart drill read aloud.
 * The candidate says the answer ("B", the option's words, or "true") and
 * hears whether it's right and why; "Repeat", "Skip" and "Stop" work by
 * voice, and with the screen locked the headphone buttons do the same
 * (play or pause repeats, next skips).
 *
 * For walks, the gym or chores: never offered for driving (decision 4),
 * with no CarPlay or Android Auto. Speech is recognised on the phone only;
 * where the phone can't do that, the big buttons answer instead.
 */
export default function HandsFreeScreen() {
  const { t, i18n } = useTranslation();
  const language: Language = i18n.language === 'es' ? 'es' : 'en';
  const [phase, setPhase] = useState<Phase>('intro');
  const [attempt, setAttempt] = useState<Attempt | null>(null);
  const [at, setAt] = useState(0);
  const [heard, setHeard] = useState('');
  const [said, setSaid] = useState('');
  const [score, setScore] = useState(0);
  const [results, setResults] = useState<Results | null>(null);
  const [voice, setVoice] = useState(true);
  const [failed, setFailed] = useState(false);
  const remote = useRef<Remote | null>(null);
  // The handlers below read the latest state through this, because speech and lock-screen callbacks outlive renders.
  const live = useRef<{ attempt: Attempt | null; at: number; phase: Phase; misses: number; voice: boolean }>({ attempt: null, at: 0, phase: 'intro', misses: 0, voice: true });
  const go = useCallback((next: Phase) => {
    live.current.phase = next;
    setPhase(next);
  }, []);

  const current = (): Question | undefined => live.current.attempt?.questions[live.current.at];

  const listen = useCallback(() => {
    go('listening');
    setHeard('');
    if (!live.current.voice) return;
    ExpoSpeechRecognitionModule.start({ lang: voiceLanguage(language), interimResults: true, requiresOnDeviceRecognition: true, addsPunctuation: false, continuous: false });
  }, [language, go]);

  const speak = useCallback(
    (text: string, then: () => void) => {
      go('speaking');
      setSaid(text);
      void Speech.stop().then(() => Speech.speak(text, { language: voiceLanguage(language), rate: 0.95, onDone: then, onStopped: () => undefined }));
    },
    [language, go],
  );

  const ask = useCallback(
    (index: number) => {
      const a = live.current.attempt;
      const q = a?.questions[index];
      if (!a || !q) return;
      live.current.misses = 0;
      live.current.at = index;
      setAt(index);
      remote.current?.title(t('practice.handsFree.lockTitle', { n: q.n, total: a.total }));
      speak(questionText({ n: q.n, total: a.total, prompt: q.prompt, options: q.options }, t), listen);
    },
    [speak, listen, t],
  );

  const finish = useCallback(async () => {
    ExpoSpeechRecognitionModule.stop();
    remote.current?.stop();
    remote.current = null;
    go('done');
    const a = live.current.attempt;
    const done = a ? await submitTest(a.id).catch(() => null) : null;
    setResults(done);
    const text = done ? t('practice.handsFree.say.done', { correct: done.correct, total: done.total }) : t('practice.handsFree.say.stopped');
    setSaid(text);
    void Speech.speak(text, { language: voiceLanguage(language) });
  }, [t, language, go]);

  const next = useCallback(() => {
    const a = live.current.attempt;
    if (!a) return;
    if (live.current.at + 1 >= a.questions.length) void finish();
    else ask(live.current.at + 1);
  }, [ask, finish]);

  const answer = useCallback(
    async (choice: number) => {
      const a = live.current.attempt;
      const q = current();
      if (!a || !q || live.current.phase === 'checking') return;
      ExpoSpeechRecognitionModule.stop();
      go('checking');
      const saved = await saveAnswer(a.id, q.id, choice, false).catch(() => null);
      if (!saved?.feedback) {
        setFailed(true);
        return speak(t('practice.handsFree.say.notSaved'), listen);
      }
      setFailed(false);
      if (saved.feedback.correct) setScore((s) => s + 1);
      speak(feedbackText(saved.feedback, q.options, t), next);
    },
    [speak, listen, next, t, go],
  );

  const repeat = useCallback(() => {
    if (live.current.phase === 'intro' || live.current.phase === 'done') return;
    ExpoSpeechRecognitionModule.stop();
    ask(live.current.at);
  }, [ask]);

  const skip = useCallback(() => {
    if (live.current.phase === 'intro' || live.current.phase === 'done') return;
    ExpoSpeechRecognitionModule.stop();
    void Speech.stop();
    next();
  }, [next]);

  const onHeard = useCallback(
    (text: string) => {
      const q = current();
      if (!q || live.current.phase !== 'listening') return;
      const meaning = understand(text, q.options, language);
      if (meaning.kind === 'answer') return void answer(meaning.choice);
      if (meaning.kind === 'repeat') return repeat();
      if (meaning.kind === 'skip') return skip();
      if (meaning.kind === 'stop') return void finish();
      live.current.misses += 1;
      if (live.current.misses > 2) return repeat();
      speak(t('practice.handsFree.say.unclear'), listen);
    },
    [language, answer, repeat, skip, finish, speak, listen, t],
  );

  useSpeechRecognitionEvent('result', (event) => {
    const text = event.results[0]?.transcript ?? '';
    setHeard(text);
    if (event.isFinal) onHeard(text);
  });
  useSpeechRecognitionEvent('nomatch', () => onHeard(''));
  useSpeechRecognitionEvent('error', (event) => {
    if (event.error === 'not-allowed' || event.error === 'service-not-allowed' || event.error === 'language-not-supported') {
      live.current.voice = false;
      setVoice(false);
    }
    else if (event.error === 'no-speech' || event.error === 'speech-timeout') onHeard('');
  });

  const begin = async () => {
    setFailed(false);
    let canListen = ExpoSpeechRecognitionModule.isRecognitionAvailable() && ExpoSpeechRecognitionModule.supportsOnDeviceRecognition();
    if (canListen) canListen = (await ExpoSpeechRecognitionModule.requestPermissionsAsync()).granted;
    live.current.voice = canListen;
    setVoice(canListen);
    const started = await startTest('drill', null, 'practice').catch(() => null);
    if (!started) return setFailed(true);
    live.current.attempt = started;
    setAttempt(started);
    setScore(0);
    setResults(null);
    remote.current = await startRemote(t('practice.handsFree.title'), repeat, skip).catch(() => null);
    ask(0);
  };

  useEffect(
    () => () => {
      ExpoSpeechRecognitionModule.stop();
      void Speech.stop();
      remote.current?.stop();
    },
    [],
  );

  const q = attempt?.questions[at];

  return (
    <Screen>
      <TitleBar title={t('practice.handsFree.title')} onBack={() => router.back()} />
      {phase === 'intro' ? (
        <>
          <Text tone="muted">{t('practice.handsFree.description')}</Text>
          <Callout tone="warning" title={t('practice.handsFree.notDriving')} body={t('practice.handsFree.notDrivingBody')} />
          <Card>
            <View style={styles.row}>
              <Icon icon={Headphones} color="action" />
              <Text weight="bold" style={styles.flex}>
                {t('practice.handsFree.howTitle')}
              </Text>
            </View>
            <Text>{t('practice.handsFree.how')}</Text>
            <Text tone="muted">{t('practice.handsFree.buttons')}</Text>
            <Text tone="muted">{t('practice.handsFree.privacy')}</Text>
          </Card>
          {failed ? <Callout tone="danger" title={t('common.tryAgain')} live /> : null}
          <Button icon={Mic} label={t('practice.handsFree.start')} onPress={() => void begin()} fullWidth />
        </>
      ) : phase === 'done' ? (
        <>
          <Card>
            <Text variant="title">{results ? t('practice.handsFree.score', { correct: results.correct, total: results.total }) : t('practice.handsFree.stopped')}</Text>
            <Text tone="muted">{said}</Text>
          </Card>
          {results ? <Button label={t('practice.seeResults')} onPress={() => router.replace({ pathname: '/practice/results/[id]', params: { id: String(results.id) } })} fullWidth /> : null}
          <Button variant="secondary" label={t('practice.handsFree.again')} onPress={() => void begin()} fullWidth />
        </>
      ) : (
        <>
          <Text tone="muted" accessibilityLiveRegion="polite">
            {q ? t('practice.handsFree.progress', { n: q.n, total: attempt?.total ?? 0, score }) : ''}
          </Text>
          <Pressable
            accessibilityRole="button"
            accessibilityLabel={phase === 'listening' ? t('practice.handsFree.listening') : t('practice.handsFree.repeat')}
            onPress={phase === 'listening' ? undefined : repeat}
            style={styles.mic(phase === 'listening')}
          >
            <Icon icon={voice ? Mic : MicOff} color={phase === 'listening' ? 'onAction' : 'action'} size={56} />
          </Pressable>
          <Text variant="bodyLarge" weight="semibold" style={styles.center} accessibilityLiveRegion="polite">
            {phase === 'listening' ? (voice ? t('practice.handsFree.listening') : t('practice.handsFree.tapAnswer')) : phase === 'checking' ? t('practice.handsFree.checking') : t('practice.handsFree.speaking')}
          </Text>
          {heard ? <Text tone="muted" style={styles.center}>{t('practice.handsFree.heard', { text: heard })}</Text> : null}
          {!voice ? <Callout tone="info" title={t('practice.handsFree.noVoice')} /> : null}
          {failed ? <Callout tone="danger" title={t('practice.handsFree.notSaved')} live /> : null}
          {q ? (
            <Card>
              <Text weight="semibold">{q.prompt}</Text>
              {q.options.map((option, i) => (
                <Button key={option} variant="secondary" label={`${LETTERS[i] ?? ''}. ${option}`} disabled={phase === 'checking'} onPress={() => void answer(i)} fullWidth />
              ))}
            </Card>
          ) : null}
          <View style={styles.row}>
            <Button variant="secondary" icon={Repeat} label={t('practice.handsFree.repeat')} onPress={repeat} />
            <Button variant="secondary" icon={SkipForward} label={t('practice.handsFree.skip')} onPress={skip} />
            <Button variant="ghost" icon={Square} label={t('practice.handsFree.stop')} onPress={() => void finish()} />
          </View>
        </>
      )}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  row: { flexDirection: 'row', flexWrap: 'wrap', gap: theme.space.xs, alignItems: 'center' },
  flex: { flex: 1 },
  center: { textAlign: 'center' },
  mic: (active: boolean) => ({
    alignSelf: 'center',
    width: 160,
    height: 160,
    borderRadius: 80,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: active ? theme.colors.action : theme.colors.surface,
    borderWidth: 2,
    borderColor: theme.colors.action,
  }),
}));
