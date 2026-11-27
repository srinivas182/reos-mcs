import * as Speech from 'expo-speech';
import { ExpoSpeechRecognitionModule, useSpeechRecognitionEvent } from 'expo-speech-recognition';
import { router, useLocalSearchParams } from 'expo-router';
import { Lightbulb, Mic, MicOff } from 'lucide-react-native';
import { useCallback, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { voiceLanguage } from '@/lib/handsFree';
import { addWords, finish, lastClientLine, loadSession, type RolePlaySession, resultSpeech, say, startAssessment, startRolePlay } from '@/lib/role-play';
import { Button, Callout, Card, Icon, Screen, SectionTitle, SwitchRow, Text, TextField, TitleBar } from '@/ui';

const DIMENSIONS = ['structure', 'questions', 'objections', 'clarity', 'ask'] as const;

/**
 * One role-play on the phone (A14.1a): the client speaks (read aloud if
 * the agent wants), the agent answers by voice — recognised on the phone —
 * or by typing, then the score and three fixes, read aloud too.
 */
export default function RolePlaySessionScreen() {
  const { t, i18n } = useTranslation();
  const { id } = useLocalSearchParams<{ id: string }>();
  const [session, setSession] = useState<RolePlaySession | null>(null);
  const [text, setText] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [aloud, setAloud] = useState(true);
  const [listening, setListening] = useState(false);
  // On-device recognition only, so no audio leaves the phone.
  const [voice, setVoice] = useState(() => ExpoSpeechRecognitionModule.isRecognitionAvailable() && ExpoSpeechRecognitionModule.supportsOnDeviceRecognition());
  const spoken = useRef<string | null>(null);
  const language = voiceLanguage(i18n.language.startsWith('es') ? 'es' : 'en');

  useEffect(() => {
    if (id) void loadSession(id).then(setSession);
  }, [id]);

  useEffect(
    () => () => {
      ExpoSpeechRecognitionModule.stop();
      void Speech.stop();
    },
    [],
  );

  // Read the client's newest line, or the result, aloud.
  useEffect(() => {
    if (!aloud || !session) return;
    const line = session.status === 'scored' ? resultSpeech(session, t) : lastClientLine(session);
    if (!line || spoken.current === line) return;
    spoken.current = line;
    void Speech.stop().then(() => Speech.speak(line, { language, rate: 0.95 }));
  }, [aloud, session, language, t]);

  useSpeechRecognitionEvent('result', (event) => {
    const words = event.results[0]?.transcript ?? '';
    if (event.isFinal) setText((now) => addWords(now, words));
  });
  useSpeechRecognitionEvent('end', () => setListening(false));
  useSpeechRecognitionEvent('error', () => setListening(false));

  const listen = useCallback(async () => {
    if (listening) {
      ExpoSpeechRecognitionModule.stop();
      return;
    }
    if (!(await ExpoSpeechRecognitionModule.requestPermissionsAsync()).granted) {
      setVoice(false);
      return;
    }
    void Speech.stop();
    ExpoSpeechRecognitionModule.start({
      lang: language,
      interimResults: false,
      requiresOnDeviceRecognition: true,
      addsPunctuation: true,
      continuous: true,
    });
    setListening(true);
  }, [listening, language]);

  const run = async (work: () => Promise<RolePlaySession>, after?: (s: RolePlaySession) => void) => {
    setBusy(true);
    setError(null);
    try {
      const next = await work();
      if (after) after(next);
      else setSession(next);
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setBusy(false);
    }
  };
  const replace = (next: RolePlaySession) => router.replace({ pathname: '/role-play/[id]', params: { id: next.id } });

  if (!session)
    return (
      <Screen>
        <TitleBar title={t('roleplay.title')} onBack={() => router.back()} />
      </Screen>
    );

  const title = session.kind === 'assessment' && session.round !== null ? t('roleplay.assessment.round', { n: session.round, total: 5 }) : t(`roleplay.types.${session.type}`);
  const answered = session.turns.some((turn) => turn.role === 'agent');
  const scores = session.scores;

  return (
    <Screen>
      <TitleBar title={title} onBack={() => router.back()} />
      <Text weight="semibold">{t(`roleplay.personas.${session.persona}.name`)}</Text>
      <Text tone="muted">{t(`roleplay.personas.${session.persona}.brief`)}</Text>
      {error ? <Callout tone="danger" title={error} /> : null}

      {session.turns.map((turn, i) => (
        <Card key={i}>
          <Text tone="muted">{turn.role === 'persona' ? t('roleplay.them') : t('roleplay.you')}</Text>
          <Text>{turn.text}</Text>
        </Card>
      ))}

      <SwitchRow label={t('roleplay.readAloud')} value={aloud} onValueChange={setAloud} />

      {session.status === 'active' ? (
        <Card>
          <Text tone="muted">{t('roleplay.turnsLeft', { count: session.turns_left })}</Text>
          <TextField label={t('roleplay.answer')} hint={listening ? t('roleplay.listening') : voice ? t('roleplay.answerHint') : t('roleplay.noVoice')} value={text} maxLength={1500} multiline onChangeText={setText} />
          {voice ? <Button label={listening ? t('roleplay.stopListening') : t('roleplay.speak')} icon={listening ? MicOff : Mic} variant="secondary" onPress={() => void listen()} /> : null}
          <Button
            label={t('roleplay.send')}
            loading={busy}
            disabled={text.trim() === ''}
            onPress={() => {
              ExpoSpeechRecognitionModule.stop();
              const answer = text.trim();
              setText('');
              void run(() => say(session.id, answer));
            }}
          />
          <Button label={t('roleplay.finish')} variant="ghost" disabled={busy || !answered} onPress={() => void run(() => finish(session.id))} />
        </Card>
      ) : null}

      {session.status === 'scored' && session.score !== null && scores ? (
        <>
          <Card>
            <Text weight="semibold">{t('roleplay.score', { score: session.score })}</Text>
            <Text>{session.outcome === 'won' ? t('roleplay.won') : t('roleplay.lost')}</Text>
            {DIMENSIONS.map((d) => (
              <Text key={d} tone="muted">
                {t('roleplay.part', {
                  label: t(`roleplay.dimensions.${d}`),
                  score: scores[d],
                })}
              </Text>
            ))}
          </Card>
          <SectionTitle>{t('roleplay.fixesTitle')}</SectionTitle>
          {session.fixes.map((fix) => (
            <Card key={fix.dimension}>
              <Icon icon={Lightbulb} color="action" size={24} />
              <Text weight="semibold">{t(`roleplay.dimensions.${fix.dimension}`)}</Text>
              <Text>{fix.text}</Text>
              <Text tone="muted">{t('roleplay.tryLine', { line: fix.example })}</Text>
              <Button
                label={t('roleplay.lesson', {
                  name: t(`roleplay.lessons.${fix.lesson}`),
                })}
                variant="ghost"
                onPress={() => router.push('/learn')}
              />
            </Card>
          ))}
          <Text tone="muted">{t(`roleplay.engine.${session.engine}`)}</Text>
          {session.kind === 'assessment' ? (
            <Button
              label={session.round !== null && session.round >= 5 ? t('roleplay.seeResults') : t('roleplay.nextRound')}
              loading={busy}
              onPress={() => (session.round !== null && session.round >= 5 ? router.replace('/role-play') : void run(startAssessment, replace))}
            />
          ) : (
            <Button label={t('roleplay.again')} loading={busy} onPress={() => void run(() => startRolePlay(session.type, session.persona), replace)} />
          )}
        </>
      ) : null}
    </Screen>
  );
}
