import { CameraView, useCameraPermissions, useMicrophonePermissions } from 'expo-camera';
import { ExpoSpeechRecognitionModule, useSpeechRecognitionEvent } from 'expo-speech-recognition';
import { router, useFocusEffect } from 'expo-router';
import { useVideoPlayer, VideoView } from 'expo-video';
import { CircleStop, Video } from 'lucide-react-native';
import { useCallback, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { voiceLanguage } from '@/lib/handsFree';
import { clock, loadVideoPractice, requestReview, type VideoKind, type VideoOverview } from '@/lib/video-practice';
import { Button, Callout, Card, EmptyState, Icon, ListRow, Screen, SectionTitle, Text, TextField, TitleBar } from '@/ui';

/**
 * Video practice on the phone (A14.1b): choose a listing pitch or a video
 * intro, record up to two minutes, watch it back, check the words (heard
 * on the phone) and get the review. The video never leaves the phone.
 */
export default function VideoPracticeScreen() {
  const { t, i18n } = useTranslation();
  const [view, setView] = useState<VideoOverview | null>(null);
  const [kind, setKind] = useState<VideoKind>('listing_pitch');
  const [state, setState] = useState<'idle' | 'recording' | 'recorded'>('idle');
  const [seconds, setSeconds] = useState(0);
  const [uri, setUri] = useState<string | null>(null);
  const [transcript, setTranscript] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [cameraAllowed, askCamera] = useCameraPermissions();
  const [micAllowed, askMic] = useMicrophonePermissions();
  const camera = useRef<CameraView | null>(null);
  const started = useRef(0);
  const max = view?.max_seconds ?? 120;
  const player = useVideoPlayer(uri);

  useFocusEffect(
    useCallback(() => {
      void loadVideoPractice().then(setView);
    }, []),
  );

  useEffect(() => {
    if (state !== 'recording') return;
    const timer = setInterval(() => setSeconds((Date.now() - started.current) / 1000), 500);

    return () => clearInterval(timer);
  }, [state]);

  useSpeechRecognitionEvent('result', (event) => {
    if (event.isFinal) setTranscript((now) => [now.trim(), (event.results[0]?.transcript ?? '').trim()].filter(Boolean).join(' '));
  });

  // The words, heard on the phone from the recording; the agent can type if this phone can't.
  const listen = async (file: string) => {
    if (!ExpoSpeechRecognitionModule.isRecognitionAvailable() || !ExpoSpeechRecognitionModule.supportsOnDeviceRecognition()) return;
    if (!(await ExpoSpeechRecognitionModule.requestPermissionsAsync()).granted) return;
    try {
      ExpoSpeechRecognitionModule.start({ lang: voiceLanguage(i18n.language.startsWith('es') ? 'es' : 'en'), requiresOnDeviceRecognition: true, addsPunctuation: true, audioSource: { uri: file } });
    } catch {
      // Not every phone can read a video file; typing still works.
    }
  };

  const record = async () => {
    setError(null);
    if (!cameraAllowed?.granted && !(await askCamera()).granted) return;
    if (!micAllowed?.granted && !(await askMic()).granted) return;
    setTranscript('');
    setUri(null);
    started.current = Date.now();
    setSeconds(0);
    setState('recording');
    const video = await camera.current?.recordAsync({ maxDuration: max });
    setSeconds((Date.now() - started.current) / 1000);
    setState('recorded');
    if (video?.uri) {
      setUri(video.uri);
      void listen(video.uri);
    }
  };

  const review = async () => {
    setBusy(true);
    setError(null);
    try {
      const done = await requestReview(kind, transcript.trim(), Math.max(1, Math.round(seconds)));
      router.push({ pathname: '/video-practice/[id]', params: { id: done.id } });
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setBusy(false);
    }
  };

  return (
    <Screen>
      <TitleBar title={t('videopractice.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('videopractice.purpose')}</Text>
      {error ? <Callout tone="danger" title={error} /> : null}

      <Card>
        <Text weight="semibold">{t('videopractice.choose')}</Text>
        {(view?.kinds ?? ['listing_pitch', 'video_intro']).map((k) => (
          <Button key={k} label={t(`videopractice.kinds.${k}`)} variant={k === kind ? 'primary' : 'secondary'} disabled={state === 'recording'} onPress={() => setKind(k)} />
        ))}
        <Text tone="muted">{t(`videopractice.kindHints.${kind}`)}</Text>
      </Card>

      {state === 'recorded' && uri ? <VideoView player={player} style={styles.video} nativeControls accessibilityLabel={t('videopractice.yourRecording')} /> : <CameraView ref={camera} mode="video" facing="front" style={styles.video} />}

      {state === 'recording' ? (
        <>
          <Text weight="semibold" accessibilityLiveRegion="polite">
            {t('videopractice.recording', { seconds: clock(seconds), max: clock(max) })}
          </Text>
          <Button label={t('videopractice.stop')} icon={CircleStop} variant="destructive" onPress={() => camera.current?.stopRecording()} />
        </>
      ) : (
        <Button label={state === 'recorded' ? t('videopractice.again') : t('videopractice.record')} icon={Video} variant={state === 'recorded' ? 'secondary' : 'primary'} onPress={() => void record()} />
      )}

      {state === 'recorded' ? (
        <Card>
          <TextField label={t('videopractice.transcript')} hint={transcript.trim() === '' ? t('videopractice.noWords') : t('videopractice.transcriptHint')} value={transcript} maxLength={5000} multiline onChangeText={setTranscript} />
          <Button label={t('videopractice.getReview')} loading={busy} disabled={transcript.trim() === ''} onPress={() => void review()} />
        </Card>
      ) : null}

      <SectionTitle>{t('videopractice.recent')}</SectionTitle>
      {view && view.recent.length === 0 ? <EmptyState icon={<Icon icon={Video} color="action" size={32} />} title={t('videopractice.none')} description={t('videopractice.noneHint')} /> : null}
      {view?.recent.map((item) => (
        <ListRow
          key={item.id}
          icon={Video}
          tone="info"
          title={t(`videopractice.kinds.${item.kind}`)}
          subtitle={`${t('videopractice.score', { score: item.score })} · ${t('videopractice.pace.value', { wpm: item.wpm })}`}
          onPress={() => router.push({ pathname: '/video-practice/[id]', params: { id: item.id } })}
        />
      ))}
      <Text tone="muted">{t('videopractice.private')}</Text>
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  video: { width: '100%', aspectRatio: 3 / 4, borderRadius: theme.radius.lg, overflow: 'hidden', backgroundColor: theme.colors.surfaceMuted },
}));
