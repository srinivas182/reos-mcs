import * as Crypto from 'expo-crypto';
import * as Speech from 'expo-speech';
import { ExpoSpeechRecognitionModule, useSpeechRecognitionEvent } from 'expo-speech-recognition';
import { router, useLocalSearchParams } from 'expo-router';
import { Headphones, Pause, Play } from 'lucide-react-native';
import { useEffect, useRef, useState } from 'react';
import { AppState } from 'react-native';
import { useTranslation } from 'react-i18next';

import { voiceLanguage } from '@/lib/handsFree';
import { answerAloud, audioBeat, choiceFromSpeech, loadListening, type Listening, type ListenState, speakable } from '@/lib/listen';
import { BEAT_MS, spent } from '@/lib/player';
import { Button, Callout, Card, Icon, Screen, Text, TitleBar } from '@/ui';

const zone = () => Intl.DateTimeFormat().resolvedOptions().timeZone ?? null;

type Clock = { spoken: { current: number }; started: { current: number | null } };

/** Stops the listening clock, keeping the time read aloud so far. */
function stopClock({ spoken, started }: Clock): void {
  if (started.current) spoken.current += Date.now() - started.current;
  started.current = null;
}

/** Seconds read aloud so far, for the heartbeat's position. */
function readSeconds({ spoken, started }: Clock): number {
  return Math.floor((spoken.current + (started.current ? Date.now() - started.current : 0)) / 1000);
}

/**
 * Listen (A14.2, ADR 0062): the lesson read aloud for the car or a walk.
 * Seat time is sent with `mode: audio`, so it counts with the screen off
 * where the course allows it. A check-in pauses the reading and is asked
 * out loud; the learner answers by voice (heard on the phone) or a tap.
 */
export default function ListenScreen() {
  const { t, i18n } = useTranslation();
  const { id } = useLocalSearchParams<{ id: string }>();
  const lessonId = Number(id);
  const [lesson, setLesson] = useState<Listening | null>(null);
  const [state, setState] = useState<ListenState | null>(null);
  const [playing, setPlaying] = useState(false);
  const [piece, setPiece] = useState(0);
  const [heard, setHeard] = useState<string | null>(null);
  const [feedback, setFeedback] = useState<string | null>(null);
  const [started, setStarted] = useState(false);
  const viewing = useRef(Crypto.randomUUID());
  const spokenMs = useRef(0);
  const startedAt = useRef<number | null>(null);
  const asked = useRef<string | null>(null);
  const language = voiceLanguage(i18n.language.startsWith('es') ? 'es' : 'en');

  useEffect(() => {
    void loadListening(lessonId).then((l) => {
      setLesson(l);
      setState(l?.player ?? null);
    });

    return () => {
      void Speech.stop();
      ExpoSpeechRecognitionModule.stop();
    };
  }, [lessonId]);

  const pieces = lesson ? lesson.sections.flatMap((s) => speakable(`${s.heading ? `${s.heading}. ` : ''}${s.text}`)) : [];
  const checkin = state?.checkin ?? null;

  const clock: Clock = { spoken: spokenMs, started: startedAt };

  const pause = () => {
    stopClock(clock);
    setPlaying(false);
    void Speech.stop();
  };

  // A check-in pauses the reading and is asked out loud, then the phone listens.
  const askAloud = (due: NonNullable<ListenState['checkin']>) => {
    const question = due.question;
    const said = question ? [question.prompt, ...question.options.map((o, i) => t('learn.listen.option', { n: i + 1, text: o })), t('learn.listen.say')].join(' ') : t('learn.listen.presence');
    Speech.speak(said, {
      language,
      onDone: () => {
        if (ExpoSpeechRecognitionModule.isRecognitionAvailable() && ExpoSpeechRecognitionModule.supportsOnDeviceRecognition()) {
          void ExpoSpeechRecognitionModule.requestPermissionsAsync().then((p) => p.granted && ExpoSpeechRecognitionModule.start({ lang: language, requiresOnDeviceRecognition: true, interimResults: false }));
        }
      },
    });
  };

  const onState = (next: ListenState) => {
    setState(next);
    if (next.checkin && asked.current !== next.checkin.key) {
      asked.current = next.checkin.key;
      pause();
      askAloud(next.checkin);
    }
  };

  // While playing, read the current piece; the next follows when it's done, and the reading stops after the last.
  const text = pieces[piece];
  const total = pieces.length;
  useEffect(() => {
    if (!playing || text === undefined) return;
    startedAt.current ??= Date.now();
    Speech.speak(text, {
      language,
      onDone: () => {
        if (piece + 1 >= total) {
          stopClock({ spoken: spokenMs, started: startedAt });
          setPlaying(false);
        } else setPiece(piece + 1);
      },
    });

    return () => void Speech.stop();
  }, [playing, text, piece, total, language]);

  // A heartbeat every 15 seconds while listening.
  const beat = useRef<() => void>(() => undefined);
  useEffect(() => {
    beat.current = () => void audioBeat(lessonId, { position: readSeconds(clock), playing: playing && !checkin, visible: AppState.currentState === 'active', session: viewing.current, timezone: zone() }).then((s) => s && onState(s));
  });
  useEffect(() => {
    if (!lesson) return;
    const timer = setInterval(() => beat.current(), BEAT_MS);

    return () => clearInterval(timer);
  }, [lesson]);

  const answer = async (choice: number | null) => {
    if (!checkin) return;
    ExpoSpeechRecognitionModule.stop();
    const result = await answerAloud(lessonId, checkin.key, choice);
    if (!result) return;
    const said = result.correct === null ? null : result.correct ? t('learn.listen.correct') : `${t('learn.listen.wrong')} ${result.explanation ?? ''}`.trim();
    setFeedback(said);
    setState(result.state);
    asked.current = null;
    if (said) Speech.speak(said, { language, onDone: () => setPlaying(true) });
    else setPlaying(true);
  };

  useSpeechRecognitionEvent('result', (event) => {
    if (!event.isFinal || !checkin) return;
    const words = event.results[0]?.transcript ?? '';
    setHeard(words);
    if (!checkin.question) {
      if (words.trim() !== '') void answer(null);
      return;
    }
    const choice = choiceFromSpeech(words, checkin.question.options);
    if (choice === null) Speech.speak(t('learn.listen.again'), { language });
    else void answer(choice);
  });

  if (!lesson || !state)
    return (
      <Screen>
        <TitleBar title={t('learn.listen.title')} onBack={() => router.back()} />
      </Screen>
    );

  return (
    <Screen>
      <TitleBar title={t('learn.listen.title')} onBack={() => router.back()} />
      <Text weight="semibold">{lesson.title}</Text>
      <Text tone="muted">{lesson.course}</Text>
      <Text tone="muted">{t('learn.listen.purpose')}</Text>
      {!lesson.audio_allowed ? <Callout tone="warning" title={t('learn.listen.notAllowed')} /> : null}
      {pieces.length === 0 ? <Callout tone="info" title={t('learn.listen.noText')} /> : null}

      <Card>
        <Icon icon={Headphones} color="action" size={32} />
        <Text>{t('learn.listen.length', { minutes: Math.max(1, Math.round(lesson.seconds / 60)) })}</Text>
        {pieces.length > 0 ? <Text tone="muted">{t('learn.listen.reading', { n: piece + 1, total: pieces.length })}</Text> : null}
        <Text>{t('learn.listen.counted', { counted: spent(state.counted), required: spent(state.required) })}</Text>
        {state.reason && state.reason !== 'checkin_due' ? <Text tone="muted">{t(`player.reasons.${state.reason}`)}</Text> : null}
        {state.completed ? <Callout tone="success" title={t('learn.listen.done')} /> : null}
        {pieces.length > 0 && !checkin ? (
          <Button
            label={playing ? t('learn.listen.pause') : started ? t('learn.listen.resume') : t('learn.listen.start')}
            icon={playing ? Pause : Play}
            onPress={() => {
              if (playing) pause();
              else {
                setStarted(true);
                setPlaying(true);
              }
            }}
          />
        ) : null}
      </Card>

      {checkin ? (
        <Card>
          <Text weight="semibold">{t('learn.listen.checkin')}</Text>
          {checkin.question ? (
            <>
              <Text>{checkin.question.prompt}</Text>
              <Text tone="muted">{t('learn.listen.say')}</Text>
              {checkin.question.options.map((option, i) => (
                <Button key={option} label={t('learn.listen.option', { n: i + 1, text: option })} variant="secondary" onPress={() => void answer(i)} />
              ))}
            </>
          ) : (
            <>
              <Text>{t('learn.listen.presence')}</Text>
              <Button label={t('learn.listen.here')} onPress={() => void answer(null)} />
            </>
          )}
          {heard ? <Text tone="muted">{t('learn.listen.heard', { words: heard })}</Text> : null}
        </Card>
      ) : null}
      {feedback ? <Text tone="muted">{feedback}</Text> : null}
    </Screen>
  );
}
