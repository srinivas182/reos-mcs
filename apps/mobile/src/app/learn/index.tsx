import { router } from 'expo-router';
import { BookOpen, FileText, Presentation, Search, Video } from 'lucide-react-native';
import { useState } from 'react';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { marks, searchLessons, type SearchHit, useLearnCourses, where } from '@/lib/learn';
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
  const [query, setQuery] = useState('');
  const [results, setResults] = useState<SearchHit[] | null>(null);
  const [searched, setSearched] = useState('');
  const [busy, setBusy] = useState(false);

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
      <TitleBar title={t('learn.title')} onBack={() => router.back()} />

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
            {[t(`content.types.${course.type}`), course.hours !== null ? t('learn.hours', { count: course.hours }) : null, t('learn.lessons', { count: course.lessons.length })].filter(Boolean).join(' · ')}
          </Text>
          {course.lessons.map((lesson) => (
            <ListRow
              key={lesson.id}
              icon={BookOpen}
              tone="info"
              title={lesson.title}
              subtitle={[lesson.minutes !== null ? t('learn.minutes', { count: lesson.minutes }) : null, lesson.language !== 'en' ? t(`content.languages.${lesson.language}`) : null].filter(Boolean).join(' · ') || undefined}
              onPress={() => router.push({ pathname: '/learn/[id]', params: { id: String(lesson.id) } })}
            />
          ))}
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
