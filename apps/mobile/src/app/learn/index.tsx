import type { TFunction } from 'i18next';
import { router } from 'expo-router';
import { BookOpen, Download, FileText, Headphones, Lock, Presentation, Search, Users, Video } from 'lucide-react-native';
import { useEffect, useState } from 'react';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { marks, searchLessons, type SearchHit, useLearnCourses, where } from '@/lib/learn';
import { type CoursePick, loadPicks } from '@/lib/listen';
import { useSessions } from '@/lib/sessions';
import { Button, Card, EmptyState, Icon, ListRow, Screen, SectionTitle, Text, TextField, TitleBar } from '@/ui';

const icons = { text: FileText, slide: Presentation, transcript: Video };

/**
 * Learn (candidate app journey screen 8, A5.3b): search inside every lesson
 * of my courses, then my live courses for this state with their lessons.
 * A result opens the lesson at the page, moment or heading it came from.
 */
export default function Learn() {
  const { t } = useTranslation();
  const { courses, state } = useLearnCourses();
  const { list: sessions } = useSessions();
  const [query, setQuery] = useState('');
  const [results, setResults] = useState<SearchHit[] | null>(null);
  const [searched, setSearched] = useState('');
  const [busy, setBusy] = useState(false);
  const [picks, setPicks] = useState<CoursePick[]>([]);

  // Picked for an agent (A14.2): CE before renewal, then the skills practice found weak, then the mode.
  useEffect(() => {
    void loadPicks()
      .then(setPicks)
      .catch(() => setPicks([]));
  }, []);

  const search = async () => {
    const q = query.trim();
    if (q.length < 2) return;

    setBusy(true);
    setResults(await searchLessons(q).catch(() => []));
    setSearched(q);
    setBusy(false);
  };

  const open = (hit: SearchHit) =>
    router.push({
      pathname: '/learn/[id]',
      params: {
        id: String(hit.lesson_id),
        ...(hit.page !== null ? { page: String(hit.page) } : {}),
        ...(hit.seconds !== null ? { t: String(hit.seconds) } : {}),
        ...(hit.heading !== null ? { heading: hit.heading } : {}),
      },
    });

  return (
    <Screen>
      <TitleBar title={t('learn.title')} onBack={() => router.back()} action={<Button label={t('player.downloads.title')} variant="ghost" icon={Download} onPress={() => router.push('/downloads')} />} />

      {picks.length > 0 ? (
        <>
          <SectionTitle>{t('learn.picks.title')}</SectionTitle>
          {picks.map((pick) => (
            <Card key={pick.id}>
              <Text weight="semibold">{pick.title}</Text>
              <Text tone="muted">{pick.reason === 'skill' && pick.skill ? t('learn.picks.skill', { skill: t(`roleplay.lessons.${pick.skill}`) }) : t(`learn.picks.${pick.reason}`)}</Text>
              {pick.first_lesson !== null ? (
                <Button label={pick.percent > 0 ? t('learn.picks.continue', { percent: pick.percent }) : t('learn.picks.start')} variant="secondary" onPress={() => router.push({ pathname: '/learn/[id]', params: { id: String(pick.first_lesson) } })} />
              ) : null}
              {pick.audio && pick.first_lesson !== null ? <Button label={t('learn.listen.open')} variant="ghost" icon={Headphones} onPress={() => router.push({ pathname: '/learn/listen/[id]', params: { id: String(pick.first_lesson) } })} /> : null}
            </Card>
          ))}
        </>
      ) : null}

      <TextField label={t('learn.searchLabel')} value={query} onChangeText={setQuery} returnKeyType="search" onSubmitEditing={() => void search()} autoCapitalize="none" />
      <Button label={t('learn.searchButton')} icon={Search} loading={busy} onPress={() => void search()} />

      {results !== null ? (
        results.length === 0 ? (
          <EmptyState icon={<Icon icon={Search} color="action" size={32} />} title={t('content.search.noResults', { query: searched })} description={t('content.search.noResultsHelp')} />
        ) : (
          <View style={styles.list}>
            {results.map((hit) => (
              <Card key={hit.passage_id} onPress={() => open(hit)} accessibilityLabel={`${hit.lesson_title}, ${where(t, hit)}. ${hit.snippet.replace(/\[\[|\]\]/g, '')}`}>
                <View style={styles.hitHead}>
                  <Icon icon={icons[hit.kind]} color="textMuted" size={20} />
                  <Text variant="caption" tone="muted" style={styles.flex}>
                    {`${hit.course_title} · ${hit.lesson_title} · ${where(t, hit)}`}
                  </Text>
                </View>
                <Text>
                  {marks(hit.snippet).map((part, i) =>
                    part.match ? (
                      <Text key={i} weight="bold" tone="action">
                        {part.text}
                      </Text>
                    ) : (
                      part.text
                    ),
                  )}
                </Text>
              </Card>
            ))}
          </View>
        )
      ) : null}

      <SectionTitle>{t('learn.coursesTitle')}</SectionTitle>
      {courses !== null && courses.length === 0 ? (
        <EmptyState icon={<Icon icon={BookOpen} color="action" size={32} />} title={t('learn.emptyTitle', { state: state ?? '' })} description={t('learn.empty')} />
      ) : null}
      {(courses ?? []).map((course) => (
        <View key={course.id} style={styles.list}>
          <Text variant="bodyLarge" weight="semibold">
            {course.title}
          </Text>
          <Text variant="caption" tone="muted">
            {[courseLabel(t, course, state ?? ""), course.hours !== null ? t('learn.hours', { count: course.hours }) : null, t('learn.lessons', { count: course.lessons.length })].filter(Boolean).join(' · ')}
          </Text>
          {course.lessons.map((lesson) => (
            <ListRow
              key={lesson.id}
              icon={lesson.locked ? Lock : BookOpen}
              tone={lesson.locked ? 'neutral' : 'info'}
              title={lesson.title}
              subtitle={[lesson.minutes !== null ? t('learn.minutes', { count: lesson.minutes }) : null, lesson.language !== 'en' ? t(`content.languages.${lesson.language}`) : null].filter(Boolean).join(' · ') || undefined}
              onPress={() => (lesson.locked ? router.push('/membership') : router.push({ pathname: '/learn/[id]', params: { id: String(lesson.id) } }))}
            />
          ))}
          {/* The course's live classes (A10.2b, L12), once video rooms are on. */}
          {sessions !== null ? (
            <ListRow icon={Users} tone="info" title={t('learn.liveClasses')} onPress={() => router.push({ pathname: '/classes', params: { course: String(course.id) } })} />
          ) : null}
        </View>
      ))}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  list: { gap: theme.space.sm },
  hitHead: { flexDirection: 'row', alignItems: 'center', gap: theme.space.xs },
  flex: { flex: 1 },
}));

/** No CE or qualifying label without an approval in force in the state (A7.1). */
function courseLabel(t: TFunction, course: { type: string; credit: { number: string } | null }, state: string): string {
  const type = t(`content.types.${course.type as 'ce'}`);

  if (course.credit) return t('learn.credit', { type, number: course.credit.number });

  return course.type === 'ce' || course.type === 'qualifying' ? t('learn.noCredit', { state }) : type;
}
