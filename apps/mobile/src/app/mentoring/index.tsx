import { router, useFocusEffect } from 'expo-router';
import { CalendarClock, ClipboardCheck, GraduationCap, UserRound, Users } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { slotText } from '@/lib/bookings';
import { loadMentees, loadMentorToday, type Mentee, type MenteeFilter, type MentorToday } from '@/lib/mentoring';
import { EmptyState, Icon, ListRow, PillGroup, Screen, SectionTitle, Text, TitleBar, type Tone } from '@/ui';

type Tab = 'today' | 'mentees';

const SIGNAL_TONE: Record<Mentee['signal'], Tone> = { quiet: 'warning', first_session: 'info', behind: 'warning', ok: 'success' };

/**
 * The mentor's workspace (mentor screens 22 and 23; A13.1b): Today (who
 * needs you, upcoming sessions, wrap-ups to send) and Mentees (all,
 * assigned or booked). Brokerage-scoped.
 */
export default function MentorWorkspaceScreen() {
  const { t, i18n } = useTranslation();
  const [tab, setTab] = useState<Tab>('today');
  const [filter, setFilter] = useState<MenteeFilter>('all');
  const [today, setToday] = useState<MentorToday | null>(null);
  const [mentees, setMentees] = useState<Mentee[] | null>(null);

  useFocusEffect(
    useCallback(() => {
      void loadMentorToday().then(setToday);
    }, []),
  );
  useFocusEffect(
    useCallback(() => {
      if (tab === 'mentees') void loadMentees(filter).then(setMentees);
    }, [tab, filter]),
  );

  const open = (m: { id: number }) => router.push({ pathname: '/mentoring/[agent]', params: { agent: String(m.id) } });
  const menteeRow = (m: Mentee) => (
    <ListRow
      key={m.id}
      icon={UserRound}
      tone={SIGNAL_TONE[m.signal]}
      title={m.name}
      subtitle={[t(`mentoring.workspace.signals.${m.signal}`), m.assigned ? t('mentoring.workspace.assigned') : null, m.next_session ? t('mentoring.workspace.nextSession', { time: slotText(m.next_session, i18n.language) }) : null].filter(Boolean).join(' · ')}
      onPress={() => open(m)}
    />
  );

  return (
    <Screen>
      <TitleBar title={t('mentoring.workspace.title')} onBack={() => router.back()} />
      {today ? <Text tone="muted">{t('mentoring.workspace.purpose', { brokerage: today.brokerage })}</Text> : null}
      <PillGroup<Tab> label={t('mentoring.workspace.title')} value={tab} options={(['today', 'mentees'] as const).map((x) => ({ value: x, label: t(`mentoring.workspace.${x}`) }))} onChange={setTab} />

      {tab === 'today' && today ? (
        <>
          <Text weight="semibold">{`${t('mentoring.workspace.menteeCount', { count: today.mentees })} · ${t('mentoring.workspace.sessionsWeek', { count: today.sessions_this_week })}`}</Text>
          <SectionTitle>{t('mentoring.workspace.needs')}</SectionTitle>
          {today.needs.length === 0 ? <Text tone="muted">{t('mentoring.workspace.noNeeds')}</Text> : today.needs.map(menteeRow)}
          <SectionTitle>{t('mentoring.workspace.upcoming')}</SectionTitle>
          {today.upcoming.length === 0 ? <Text tone="muted">{t('mentoring.workspace.noUpcoming')}</Text> : null}
          {today.upcoming.map((s) => (
            <ListRow key={s.uuid} icon={CalendarClock} tone="info" title={s.agent} subtitle={`${slotText(s.starts_at, i18n.language)} · ${s.topic}`} onPress={() => (s.session ? router.push({ pathname: '/sessions/[id]', params: { id: s.session } }) : open({ id: s.agent_id }))} />
          ))}
          <SectionTitle>{t('mentoring.workspace.wrapUps')}</SectionTitle>
          {today.wrap_ups.length === 0 ? <Text tone="muted">{t('mentoring.workspace.noWrapUps')}</Text> : null}
          {today.wrap_ups.map((s) => (
            <ListRow key={s.uuid} icon={ClipboardCheck} tone="warning" title={`${t('mentoring.workspace.wrapUp')}: ${s.agent}`} subtitle={s.topic} onPress={() => router.push({ pathname: '/bookings/tasks/[uuid]', params: { uuid: s.uuid, name: s.agent } })} />
          ))}
        </>
      ) : null}

      {tab === 'mentees' ? (
        <>
          <PillGroup<MenteeFilter> label={t('mentoring.workspace.mentees')} value={filter} options={(['all', 'assigned', 'booked'] as const).map((x) => ({ value: x, label: t(`mentoring.workspace.filters.${x}`) }))} onChange={setFilter} />
          {mentees !== null && mentees.length === 0 ? <EmptyState icon={<Icon icon={Users} color="action" size={32} />} title={t('mentoring.workspace.noMentees')} description={t('mentoring.workspace.noMenteesHint')} /> : null}
          {mentees?.map(menteeRow)}
        </>
      ) : null}
      {today === null ? <ListRow icon={GraduationCap} tone="info" title={t('bookings.title')} onPress={() => router.push('/bookings')} /> : null}
    </Screen>
  );
}
