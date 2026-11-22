import { router, useLocalSearchParams } from 'expo-router';
import { useVideoPlayer, VideoView } from 'expo-video';
import * as WebBrowser from 'expo-web-browser';
import { ExternalLink } from 'lucide-react-native';
import { useEffect, useState } from 'react';
import { ActivityIndicator, Pressable, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';

import { clock, type LearnLesson, loadLesson } from '@/lib/learn';
import { Button, Card, Chip, EmptyState, Screen, SectionTitle, Text, TitleBar } from '@/ui';

const number = (value: string | string[] | undefined): number | null => {
  const parsed = typeof value === 'string' ? Number.parseInt(value, 10) : Number.NaN;

  return Number.isNaN(parsed) ? null : parsed;
};

/**
 * A lesson (A5.3b): opened at the moment, page or heading a search result
 * points to. What the result matched is shown first ("From your search"),
 * then the video with its transcript (tap a line to play from there), the
 * slides page by page, and the lesson text. The full player with check-ins
 * and downloads is screen 9 (Sprint 8).
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

  useEffect(() => {
    if (id === null) return;
    let active = true;
    void loadLesson(id)
      .catch(() => null)
      .then((found) => active && setLesson(found));

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

      {lesson.video || lesson.transcript.length > 0 ? <Player lesson={lesson} startAt={startAt} /> : null}

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

/** The video, starting at the search result's moment, and its transcript: tap a line to play from there. */
function Player({ lesson, startAt }: { lesson: LearnLesson; startAt: number | null }) {
  const { t } = useTranslation();
  const player = useVideoPlayer(lesson.video?.url ?? null, (p) => {
    if (startAt !== null) p.currentTime = startAt;
  });

  const jump = (seconds: number) => {
    player.seekBy(seconds - player.currentTime);
    player.play();
  };

  return (
    <View style={styles.block}>
      {lesson.video ? (
        <>
          <VideoView player={player} style={styles.video} nativeControls allowsPictureInPicture accessibilityLabel={lesson.title} />
          {startAt !== null ? <Text tone="muted">{t('learn.lesson.startsAt', { time: clock(startAt) })}</Text> : null}
        </>
      ) : null}
      {lesson.transcript.length > 0 ? (
        <>
          <SectionTitle>{t('learn.lesson.transcript')}</SectionTitle>
          {lesson.transcript.map((cue, i) => (
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
          ))}
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
  time: { width: 56 },
  flex: { flex: 1 },
}));
