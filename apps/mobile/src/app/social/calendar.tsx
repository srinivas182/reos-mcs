import { router, useFocusEffect } from 'expo-router';
import { CalendarPlus, ChevronLeft, ChevronRight, Repeat2 } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { addDays, type Calendar, type CalendarPost, loadCalendar, movePost, reusePost } from '@/lib/content-calendar';
import { Button, Callout, Card, Chip, EmptyState, Icon, IconButton, PillGroup, Screen, SectionTitle, Stepper, Text, TitleBar, type Tone, useUndoToast } from '@/ui';

type View_ = 'week' | 'month';

const TONE: Record<string, Tone> = { scheduled: 'info', published: 'success', failed: 'danger', partly: 'warning', approval: 'warning', draft: 'neutral', publishing: 'info' };

/**
 * Content calendar on the phone (B12.3; from My posts): a week or a month
 * of posts, day by day. Move a post a day earlier or later (with Undo),
 * reuse one a week later, or plan a post for a day.
 */
export default function ContentCalendarScreen() {
  const { t, i18n } = useTranslation();
  const toast = useUndoToast();
  const [view, setView] = useState<View_>('week');
  const [date, setDate] = useState<string | undefined>(undefined);
  const [calendar, setCalendar] = useState<Calendar | null>(null);
  const [busy, setBusy] = useState(false);
  const [problem, setProblem] = useState<string | null>(null);

  const load = useCallback((v: View_, d?: string) => void loadCalendar(v, d).then(setCalendar), []);
  useFocusEffect(useCallback(() => load(view, date), [load, view, date]));

  const label = (d: string, weekday = true) =>
    new Intl.DateTimeFormat(i18n.language, { ...(weekday ? { weekday: 'long' } : {}), month: 'long', day: 'numeric', timeZone: 'UTC' }).format(new Date(`${d}T12:00:00Z`));
  const act = async (work: () => Promise<unknown>, done: () => void) => {
    setBusy(true);
    setProblem(null);
    try {
      await work();
      done();
    } catch (e) {
      setProblem(e instanceof Error ? e.message : String(e));
    } finally {
      setBusy(false);
    }
  };
  const move = (post: CalendarPost, from: string, by: number) => {
    const to = addDays(from, by);
    void act(
      () => movePost(post.id, to),
      () => {
        load(view, date);
        toast({ message: t('social.calendar.moved', { date: label(to, false) }), onUndo: async () => { await movePost(post.id, from); load(view, date); } });
      },
    );
  };
  const reuse = (post: CalendarPost, from: string) => {
    const today = new Date().toISOString().slice(0, 10);
    const later = addDays(from, 7);
    const to = later < today ? today : later;
    void act(
      () => reusePost(post.id, to),
      () => {
        load(view, date);
        toast({ message: t('social.calendar.reused', { date: label(to, false) }) });
      },
    );
  };

  if (!calendar) return <Screen>{null}</Screen>;

  const empty = calendar.days.every((d) => d.posts.length === 0);
  const days = calendar.view === 'month' ? calendar.days.filter((d) => d.posts.length > 0 || d.today) : calendar.days;

  return (
    <Screen>
      <TitleBar title={t('social.calendar.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('social.calendar.purpose')}</Text>
      <PillGroup<View_> label={t('social.calendar.title')} value={view} options={(['week', 'month'] as const).map((v) => ({ value: v, label: t(`social.calendar.${v}`) }))} onChange={setView} />
      <View style={styles.nav}>
        <IconButton icon={ChevronLeft} label={t('social.calendar.previous')} onPress={() => setDate(calendar.previous)} />
        <Text weight="semibold" style={styles.flex} accessibilityLiveRegion="polite">
          {`${label(calendar.from, false)} – ${label(calendar.to, false)}`}
        </Text>
        <IconButton icon={ChevronRight} label={t('social.calendar.next')} onPress={() => setDate(calendar.next)} />
      </View>
      <Text tone="muted">{t('social.calendar.scheduled', { count: calendar.scheduled })}</Text>
      {problem ? <Callout tone="danger" title={problem} live /> : null}
      {empty ? <EmptyState icon={<Icon icon={CalendarPlus} color="action" size={32} />} title={t('social.calendar.empty')} description={t('social.calendar.emptyHint')} /> : null}

      {days.map((day) => (
        <View key={day.date} style={styles.day}>
          <SectionTitle>{day.today ? `${label(day.date)} · ${t('social.calendar.today')}` : label(day.date)}</SectionTitle>
          {day.posts.map((post) => (
            <Card key={post.id} style={styles.gap} onPress={() => router.push({ pathname: '/social/[id]', params: { id: post.id } })} accessibilityLabel={`${t(`social.calendar.status.${post.status as 'draft'}`)}. ${post.body}`}>
              <View style={styles.row}>
                <Chip tone={TONE[post.status] ?? 'neutral'} label={t(`social.calendar.status.${post.status as 'draft'}`)} />
                {post.time ? <Text tone="muted">{post.time}</Text> : null}
              </View>
              <Text>{post.body}</Text>
              {post.movable ? (
                <Stepper
                  label={t('social.calendar.moveTo')}
                  value={label(day.date)}
                  earlier={t('social.calendar.previous')}
                  later={t('social.calendar.next')}
                  onEarlier={() => (busy ? undefined : move(post, day.date, -1))}
                  onLater={() => (busy ? undefined : move(post, day.date, 1))}
                />
              ) : null}
              <Chip icon={Repeat2} label={t('social.calendar.reuse')} onPress={busy ? undefined : () => reuse(post, day.date)} />
            </Card>
          ))}
          {calendar.view === 'week' ? <Button variant="ghost" icon={CalendarPlus} label={t('social.calendar.plan')} onPress={() => router.push({ pathname: '/social/new', params: { at: `${day.date}T10:00` } })} /> : null}
        </View>
      ))}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  nav: { flexDirection: 'row', alignItems: 'center', gap: theme.space.sm },
  flex: { flex: 1, textAlign: 'center' },
  day: { gap: theme.space.sm },
  gap: { gap: theme.space.sm },
  row: { flexDirection: 'row', alignItems: 'center', gap: theme.space.sm },
}));
