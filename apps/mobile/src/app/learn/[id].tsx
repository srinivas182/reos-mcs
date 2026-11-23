import { router, useLocalSearchParams } from 'expo-router';
import { useVideoPlayer, VideoView } from 'expo-video';
import * as WebBrowser from 'expo-web-browser';
import { CircleCheck, Download, ExternalLink, Headphones, RotateCcw, Settings2 } from 'lucide-react-native';
import { useEffect, useState } from 'react';
import { ActivityIndicator, Pressable, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';

import { AskInstructor } from '@/features/player/AskInstructor';
import { CheckinSheet } from '@/features/player/CheckinSheet';
import { PlaybackSheet } from '@/features/player/PlaybackSheet';
import { SeatMeter } from '@/features/player/SeatMeter';
import { clock, type LearnLesson, loadLesson } from '@/lib/learn';
import { offlineLesson, playable, SaveError, type SavedLesson, saveLesson, savedFor, smartDownload } from '@/lib/offline';
import { effectiveQuality, streamFor, useCellular, usePlayback } from '@/lib/playback';
import { configurePlayer, SPEEDS, useSeatTime } from '@/lib/player';
import { Button, Callout, Card, Chip, EmptyState, Icon, Screen, SectionTitle, Text, TitleBar } from '@/ui';

const number = (value: string | string[] | undefined): number | null => {
  const parsed = typeof value === 'string' ? Number.parseInt(value, 10) : Number.NaN;

  return Number.isNaN(parsed) ? null : parsed;
};

/**
 * A lesson (A5.3b, A8.2; candidate screen 25, the player): opened at the
 * moment, page or heading a search result points to. What the result
 * matched is shown first ("From your search"), then the player (seat meter,
 * check-ins, video and controls, transcript), the slides page by page, and
 * the lesson text. Offline, a saved lesson plays from the phone.
 */
export default function LessonScreen() {
  const { t } = useTranslation();
  const { theme } = useUnistyles();
  const params = useLocalSearchParams<{ id: string; page?: string; t?: string; heading?: string }>();
  const id = number(params.id);
  const page = number(params.page);
  const startAt = number(params.t);
  const heading = typeof params.heading === 'string' ? params.heading : null;
  const [lesson, setLesson] = useState<LearnLesson | null | undefined>(undefined);
  const [saved, setSaved] = useState<SavedLesson | undefined>(() => (id === null ? undefined : savedFor(id)));

  useEffect(() => {
    if (id === null) return;
    let active = true;
    void loadLesson(id)
      .catch(() => null)
      .then((found) => {
        if (!active) return;
        // Needs a plan: the Membership screen (screen 6) instead.
        if (found === 'locked') {
          router.replace('/membership');

          return;
        }
        // Offline, a saved lesson plays from the phone (A8.2b).
        const copy = savedFor(id);
        setLesson(found ?? (copy && playable(copy) ? offlineLesson(copy) : null));
        // Smart downloads: the next two lessons, on Wi-Fi.
        if (found) void smartDownload(found.next);
      });

    return () => {
      active = false;
    };
  }, [id]);

  if (lesson === undefined) {
    return (
      <Screen>
        <TitleBar title={t('learn.lesson.back')} onBack={() => router.back()} />
        <ActivityIndicator color={theme.colors.action} />
      </Screen>
    );
  }

  if (lesson === null) {
    return (
      <Screen>
        <TitleBar title={t('learn.lesson.back')} onBack={() => router.back()} />
        <EmptyState title={t('learn.lesson.empty')} description={t('learn.empty')} />
      </Screen>
    );
  }

  const document = page !== null ? lesson.documents.find((d) => d.pages.some((p) => p.page === page)) : undefined;
  const pageText = document?.pages.find((p) => p.page === page)?.text;
  const section = heading !== null && startAt === null && page === null ? lesson.sections.find((s) => s.heading === heading) : undefined;

  return (
    <Screen>
      <TitleBar title={lesson.title} onBack={() => router.back()} />
      <Text tone="muted">{lesson.course.title}</Text>

      {document && pageText !== undefined ? (
        <Card>
          <Chip label={t('learn.lesson.here')} tone="info" />
          <Text weight="semibold">{`${t(`content.kinds.${document.kind}`)} · ${t('learn.lesson.page', { page })}`}</Text>
          <Text>{pageText}</Text>
        </Card>
      ) : null}
      {section ? (
        <Card>
          <Chip label={t('learn.lesson.here')} tone="info" />
          <Text weight="semibold">{section.heading}</Text>
          <Text>{section.text}</Text>
        </Card>
      ) : null}

      <Player key={lesson.id} lesson={lesson} startAt={startAt} saved={saved} onSaved={() => setSaved(savedFor(lesson.id))} />

      {lesson.documents.map((doc) => (
        <View key={doc.id} style={styles.block}>
          <SectionTitle>{t(`content.kinds.${doc.kind}`)}</SectionTitle>
          <Button label={t('learn.lesson.open', { name: doc.name })} variant="secondary" icon={ExternalLink} onPress={() => void WebBrowser.openBrowserAsync(doc.url)} />
          {doc.pages.map((p) => (
            <Card key={p.page}>
              <Text variant="caption" weight="bold" tone="muted">
                {t('learn.lesson.page', { page: p.page })}
              </Text>
              <Text>{p.text}</Text>
            </Card>
          ))}
        </View>
      ))}

      {lesson.sections.length > 0 ? (
        <View style={styles.block}>
          <SectionTitle>{t('learn.lesson.text')}</SectionTitle>
          {lesson.sections.map((s, i) => (
            <View key={i} style={styles.block}>
              {s.heading ? (
                <Text variant="bodyLarge" weight="semibold" heading>
                  {s.heading}
                </Text>
              ) : null}
              <Text>{s.text}</Text>
            </View>
          ))}
        </View>
      ) : null}

      {lesson.next !== null ? <Button label={t('learn.lesson.next')} onPress={() => router.replace({ pathname: '/learn/[id]', params: { id: String(lesson.next) } })} /> : null}
    </Screen>
  );
}

/**
 * The player (candidate screen 25, A8.2): the video, from the phone's saved
 * copy when there is one, with Back 10 seconds, Save offline and Playback
 * settings (screen 26: quality per connection, data saver, speed, captions,
 * audio only); the seat meter and check-ins; Interrupt and ask; and the
 * transcript (tap a line to play from there).
 */
function Player({ lesson, startAt, saved, onSaved }: { lesson: LearnLesson; startAt: number | null; saved: SavedLesson | undefined; onSaved: () => void }) {
  const { t, i18n } = useTranslation();
  const local = saved && playable(saved) ? saved.uri : null;
  const playback = usePlayback();
  const cellular = useCellular();
  const quality = effectiveQuality(playback, cellular);
  const source = local ?? (lesson.video ? streamFor(lesson.video, quality) : null);
  const [settingsOpen, setSettingsOpen] = useState(false);
  const player = useVideoPlayer(source, (p) => {
    if (startAt !== null) p.currentTime = startAt;
  });
  const seat = useSeatTime(lesson, lesson.video ? player : null, saved);
  const [speed, setSpeed] = useState<(typeof SPEEDS)[number]>(1);
  const [audioOnly, setAudioOnly] = useState(false);
  const languages = (['en', 'es'] as const).filter((l) => (lesson.captions?.[l]?.length ?? 0) > 0);
  const mine = i18n.language.startsWith('es') ? 'es' : 'en';
  const [shown, setShown] = useState<'en' | 'es'>(languages.includes(mine) ? mine : (languages[0] ?? mine));
  const cues = lesson.captions?.[shown] ?? lesson.transcript;
  const [saving, setSaving] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);

  const chooseSpeed = (value: (typeof SPEEDS)[number]) => {
    setSpeed(value);
    configurePlayer(player, { rate: value });
  };

  // Audio only keeps playing with the screen locked (seat time waits until REOS is in front again).
  const chooseAudioOnly = (on: boolean) => {
    setAudioOnly(on);
    configurePlayer(player, { background: on });
  };

  const jump = (seconds: number) => {
    player.seekBy(seconds - player.currentTime);
    player.play();
  };

  const save = async () => {
    setSaving(true);
    setSaveError(null);
    try {
      await saveLesson(lesson.id);
      onSaved();
    } catch (error) {
      setSaveError(error instanceof SaveError ? t(`player.downloads.errors.${error.reason}`) : t('player.downloads.errors.server'));
    } finally {
      setSaving(false);
    }
  };

  return (
    <View style={styles.block}>
      <SeatMeter state={seat.state} offline={!seat.online} />
      <CheckinSheet checkpoint={seat.state.checkin} online={seat.online} onAnswer={seat.answer} />
      {lesson.video ? (
        <>
          {audioOnly ? (
            <Card>
              <Icon icon={Headphones} />
              <Text>{t('player.audioOnlyOn')}</Text>
            </Card>
          ) : (
            <VideoView player={player} style={styles.video} nativeControls allowsPictureInPicture accessibilityLabel={lesson.title} />
          )}
          {startAt !== null ? <Text tone="muted">{t('learn.lesson.startsAt', { time: clock(startAt) })}</Text> : null}
          <View style={styles.controls}>
            <Button label={t('player.back10')} variant="secondary" icon={RotateCcw} onPress={() => player.seekBy(-10)} />
            <Button label={t('player.settings.open')} variant="secondary" icon={Settings2} onPress={() => setSettingsOpen(true)} />
            {saved ? (
              <Chip label={playable(saved) ? t('player.downloads.saved') : t('player.downloads.expired')} tone={playable(saved) ? 'success' : 'warning'} icon={CircleCheck} />
            ) : (
              <Button label={t('player.downloads.save')} variant="secondary" icon={Download} loading={saving} onPress={() => void save()} />
            )}
          </View>
          {saveError ? <Callout tone="warning" title={saveError} live /> : null}
          <PlaybackSheet
            open={settingsOpen}
            onClose={() => setSettingsOpen(false)}
            cellular={cellular}
            speed={speed}
            onSpeed={chooseSpeed}
            languages={languages}
            captions={shown}
            onCaptions={setShown}
            audioOnly={audioOnly}
            onAudioOnly={chooseAudioOnly}
            qualities={local === null && Object.keys(lesson.video.qualities).length > 0}
          />
        </>
      ) : null}
      <AskInstructor
        lesson={lesson.id}
        pause={() => {
          if (!lesson.video) return null;
          player.pause();

          return Math.floor(player.currentTime);
        }}
        resume={(seconds) => {
          if (!lesson.video) return;
          if (seconds !== undefined) player.seekBy(seconds - player.currentTime);
          player.play();
        }}
      />
      {cues.length > 0 ? (
        <>
          <SectionTitle>{t('learn.lesson.transcript')}</SectionTitle>
          {!lesson.video ? <Text tone="muted">{t('learn.lesson.noVideo')}</Text> : null}
          {!lesson.video
            ? cues.map((cue, i) => (
                <View key={`${cue.seconds}-${i}`} style={styles.cue}>
                  <Text tone="muted" style={styles.time}>
                    {clock(cue.seconds)}
                  </Text>
                  <Text style={styles.flex}>{cue.text}</Text>
                </View>
              ))
            : null}
          {lesson.video
            ? cues.map((cue, i) => (
                <Pressable
                  key={`${cue.seconds}-${i}`}
                  accessibilityRole="button"
                  accessibilityLabel={`${t('learn.lesson.jump', { time: clock(cue.seconds) })}. ${cue.text}`}
                  onPress={() => jump(cue.seconds)}
                  style={({ pressed }) => [styles.cue, (pressed || cue.seconds === startAt) && styles.cueActive]}
                >
                  <Text weight="bold" tone="action" style={styles.time}>
                    {clock(cue.seconds)}
                  </Text>
                  <Text style={styles.flex}>{cue.text}</Text>
                </Pressable>
              ))
            : null}
        </>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create((theme) => ({
  block: { gap: theme.space.sm },
  video: { width: '100%', aspectRatio: 16 / 9, borderRadius: theme.radius.lg, backgroundColor: theme.colors.surfaceMuted },
  cue: { flexDirection: 'row', gap: theme.space.sm, minHeight: 48, paddingVertical: theme.space.xs, paddingHorizontal: theme.space.xs, borderRadius: theme.radius.md },
  cueActive: { backgroundColor: theme.colors.actionTint },
  controls: { flexDirection: 'row', flexWrap: 'wrap', gap: theme.space.sm, alignItems: 'center' },
  time: { width: 56 },
  flex: { flex: 1 },
}));
