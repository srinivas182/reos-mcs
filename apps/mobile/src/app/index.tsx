import { router, useLocalSearchParams } from 'expo-router';
import { Bell, BookOpen, GraduationCap, Handshake, MessageCircleQuestion, MessageSquare, Milestone, Palette, Users, Video } from 'lucide-react-native';
import { useEffect, useState } from 'react';
import { ActivityIndicator, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';

import { AgentToday } from '@/features/agent/AgentToday';
import { ModeCard } from '@/features/agent/ModeCard';
import { TodayAgenda } from '@/features/calendar/TodayAgenda';
import { TodayInsight } from '@/features/contacts/TodayInsight';
import { CandidateToday } from '@/features/study/CandidateToday';
import { TodayTodos } from '@/features/todos/TodayTodos';
import { type CaptureMode, VoiceCaptureSheet } from '@/features/voice/VoiceCaptureSheet';
import { useAgentToday, useMode } from '@/lib/agent';
import { fetchHealth, type Health } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import { toolInitials, useConnections } from '@/lib/connections';
import { activeMembership } from '@/lib/roles';
import { timeAgo } from '@/lib/time';
import { useOpenTodos } from '@/lib/todos';
import { useCandidateToday } from '@/lib/practice';
import { useTodayInsight } from '@/lib/crm';
import { useWhatsNew } from '@/lib/whatsNew';
import { useAnnouncements, useInbox } from '@/lib/messages';
import { nextSession, useSessions } from '@/lib/sessions';
import { sessionTime } from '@/features/sessions/SessionRow';
import { useNotificationInbox } from '@/lib/notifications';
import { usePushPrompt } from '@/lib/push';
import { AskBar, Avatar, Button, Callout, Card, IconButton, ListRow, Screen, SyncAlert, Text } from '@/ui';

type State = { kind: 'loading' } | { kind: 'ok'; health: Health } | { kind: 'error'; message: string };

/**
 * Placeholder home until Today is built (A3.3 onward): the REOS brand, your
 * initials (→ Me), a red card for any connection that stopped syncing
 * (Connections and Sync journey, screen 26), today's calendar and to-dos for
 * agents (B4.1, B4.3b) with the Ask bar's mic for voice to-dos, "What's new"
 * for your role (A6.2), a live check
 * of the platform API and, in development, the component gallery.
 *
 * `/?capture=voice` (the "Add a to-do" shortcut and `reos://voice`) opens
 * voice capture at once.
 */
export default function HomeScreen() {
  const { t, i18n } = useTranslation();
  const { theme } = useUnistyles();
  const { user } = useAuth();
  const { connections } = useConnections();
  const broken = (connections ?? []).filter((connection) => connection.status === 'reconnect_needed');
  const role = activeMembership(user)?.role;
  const agent = role === 'agent';
  const learner = role === 'agent' || role === 'candidate';
  const candidate = role === 'candidate';
  // Today for candidates (screen 12, A8.3): readiness, Continue and today's plan.
  const { today: study } = useCandidateToday(candidate);
  const { today: insight, reload: reloadInsight } = useTodayInsight(agent);
  const mode = useMode(agent);
  // Today as one ranked list, the mode's stats and day one (A11.1b).
  const agentToday = useAgentToday(agent);
  const [state, setState] = useState<State>({ kind: 'loading' });
  const [attempt, setAttempt] = useState(0);
  const { capture } = useLocalSearchParams<{ capture?: string }>();
  const [chosen, setCapturing] = useState<CaptureMode | null>(null);
  const capturing = chosen ?? (agent && (capture === 'voice' || capture === 'type') ? capture : null);
  const todos = useOpenTodos();
  const whatsNew = useWhatsNew();
  const notificationInbox = useNotificationInbox();
  // Messages once rolled out to this person (B6.1); null hides the entry.
  const { inbox } = useInbox(user?.id ?? null);
  const { list: sessions } = useSessions();
  const next = nextSession(sessions);
  // Announcements from my brokerage, school or association (B6.1b), until read.
  const { announcements, markRead } = useAnnouncements(user?.id ?? null);
  const unreadAnnouncements = (announcements ?? []).filter((a) => !a.read);
  const { show: pushPrompt, answer: answerPushPrompt } = usePushPrompt(Boolean(user));
  const closeCapture = () => {
    setCapturing(null);
    if (capture !== undefined) router.setParams({ capture: undefined });
  };

  useEffect(() => {
    let active = true;

    fetchHealth()
      .then((health) => active && setState({ kind: 'ok', health }))
      .catch((error: unknown) => {
        if (active) {
          setState({ kind: 'error', message: error instanceof Error ? error.message : 'Unknown error' });
        }
      });

    return () => {
      active = false;
    };
  }, [attempt]);

  const retry = () => {
    setState({ kind: 'loading' });
    setAttempt((n) => n + 1);
  };

  return (
    <Screen
      footer={
        agent ? (
          <AskBar placeholder={t('voice.askPlaceholder')} voiceLabel={t('voice.start')} onPress={() => setCapturing('type')} onVoice={() => setCapturing('voice')} />
        ) : candidate ? (
          // Ask, the study mate (screen 13, A10.1): answers from your courses, with the lesson to watch.
          <AskBar placeholder={t('practice.today.ask')} onPress={() => router.push('/ask')} onVoice={() => router.push('/ask')} />
        ) : undefined
      }
    >
      {/* Me opens from your initials, top right (journey screen 16). */}
      <View style={styles.top}>
        <Text variant="caption" weight="bold" tone="action">
          {t('mobile.eyebrow')}
        </Text>
        <View style={styles.topActions}>
          {/* The bell: the notification inbox (B6.2). */}
          {user ? <IconButton icon={Bell} label={notificationInbox.inbox && notificationInbox.inbox.unread > 0 ? t('notifications.bellUnread', { count: notificationInbox.inbox.unread }) : t('notifications.bell')} onPress={() => router.push('/notifications')} /> : null}
          {user ? <Avatar name={user.name ?? user.email ?? user.phone ?? '?'} label={t('mobile.me.open')} onPress={() => router.push('/me')} /> : null}
        </View>
      </View>
      {broken.map((connection) => (
        <SyncAlert
          key={connection.id}
          initials={toolInitials(connection.integration_name)}
          title={t('connections.alert.title', { tool: connection.integration_name })}
          body={t('connections.alert.body', {
            tool: connection.integration_name,
            ago: connection.problem_since ? timeAgo(connection.problem_since, i18n.language) : '',
          })}
          action={t('connections.alert.action')}
          onPress={() => router.push({ pathname: '/reconnect/[id]', params: { id: String(connection.id) } })}
        />
      ))}
      {/* What's new for your role (A6.2), once, until dismissed. */}
      {whatsNew.item ? (
        <Callout
          tone="info"
          title={t('whatsNew.title', { version: whatsNew.item.version })}
          body={whatsNew.item.note}
          action={<Button variant="ghost" label={t('whatsNew.dismiss')} accessibilityLabel={t('whatsNew.dismissLabel', { version: whatsNew.item.version })} onPress={whatsNew.dismiss} />}
        />
      ) : null}
      {/* Push (B6.2b): asked once, only when the person taps Turn on. */}
      {pushPrompt ? (
        <Callout
          tone="info"
          title={t('notifications.prompt.title')}
          body={t('notifications.prompt.body')}
          action={
            <View style={styles.promptActions}>
              <Button label={t('notifications.prompt.action')} onPress={() => void answerPushPrompt(true)} />
              <Button variant="ghost" label={t('notifications.prompt.later')} onPress={() => void answerPushPrompt(false)} />
            </View>
          }
        />
      ) : null}
      {unreadAnnouncements.map((announcement) => (
        <Callout
          key={announcement.id}
          tone="info"
          title={announcement.title}
          body={`${announcement.body}\n${t('announcements.from', { from: announcement.from, ago: timeAgo(announcement.sent_at, i18n.language) })}`}
          action={<Button variant="ghost" label={t('announcements.markRead')} accessibilityLabel={t('announcements.markReadFor', { title: announcement.title })} onPress={() => void markRead(announcement.id)} />}
        />
      ))}
      {inbox !== null ? (
        <ListRow
          icon={MessageSquare}
          tone="info"
          title={t('messages.title')}
          subtitle={inbox.unread > 0 ? t('messages.unreadCount', { count: inbox.unread }) : t('messages.allRead')}
          onPress={() => router.push('/messages')}
        />
      ) : null}
      {/* The next session (B6.3), and every session behind it. */}
      {sessions !== null ? (
        <ListRow
          icon={Video}
          tone={next?.can_join ? 'success' : 'info'}
          title={next ? next.title : t('sessions.title')}
          subtitle={next ? `${sessionTime(next, i18n.language)}${next.can_join ? ` · ${t('sessions.ready')}` : ''}` : t('sessions.empty')}
          onPress={() => (next ? router.push({ pathname: '/sessions/[id]', params: { id: next.id } }) : router.push('/sessions'))}
        />
      ) : null}
      {/* Today's calendar for agents (B4.1); the rest of Today arrives with the agent sprints. */}
      {/* Learn (candidate screen 8, A5.3b): courses and search inside lessons; the Learn tab arrives with the tab bar. */}
      {study ? <CandidateToday today={study} /> : null}
      {learner ? <ListRow icon={BookOpen} tone="info" title={t('learn.title')} subtitle={t('learn.searchLabel')} onPress={() => router.push('/learn')} /> : null}
      {/* Do this now (Agent App Journey screen 6, B8.3), and every contact. */}
      {/* The agent's mode, "Pick your goal", or a suggested switch (A11.1a). */}
      {agent && mode.mode ? <ModeCard mode={mode.mode} onChange={mode.setMode} /> : null}
      {agent && agentToday.today ? <AgentToday today={agentToday.today} onChanged={() => void agentToday.reload()} /> : null}
      {agent && insight ? <TodayInsight contact={insight.contact} insight={insight.insight} onDone={() => void reloadInsight()} /> : null}
      {/* The licence journey (A10.3a, screen 16): steps, window, exam date, result. */}
      {candidate ? <ListRow icon={Milestone} tone="info" title={t('licence.title')} subtitle={t('licence.today')} onPress={() => router.push('/licence-journey')} /> : null}
      {/* Live classes (A10.2b): for candidates their courses' classes (L12); for instructors their week (i11). */}
      {sessions !== null && (candidate || role === 'instructor') ? (
        <ListRow icon={Users} tone="info" title={t('classes.title')} subtitle={role === 'instructor' ? t('classes.purposeHost') : t('classes.purpose')} onPress={() => router.push('/classes')} />
      ) : null}
      {/* Your mentors (A10.3c, f04): the agent's own brokerage's mentors. */}
      {agent ? <ListRow icon={Users} tone="info" title={t('mentors.title')} subtitle={t('mentors.purposeNone')} onPress={() => router.push('/mentors')} /> : null}
      {/* Book an instructor (candidate screen 18) and, for instructors and mentors, their booked sessions (A10.2a). */}
      {candidate || role === 'instructor' || role === 'mentor' ? <ListRow icon={GraduationCap} tone="info" title={t('bookings.title')} subtitle={t('bookings.purpose')} onPress={() => router.push('/bookings')} /> : null}
      {/* Student questions (instructor screen i09, A10.1b). */}
      {role === 'instructor' ? <ListRow icon={MessageCircleQuestion} tone="info" title={t('ask.instructor.title')} subtitle={t('ask.instructor.purpose')} onPress={() => router.push('/instructor/questions')} /> : null}
      {agent ? <ListRow icon={Users} tone="info" title={t('crm.hub.title')} subtitle={t('crm.hub.description')} onPress={() => router.push('/contacts')} /> : null}
      {agent ? <ListRow icon={Handshake} tone="info" title={t('deals.pipeline.title')} subtitle={t('deals.pipeline.description')} onPress={() => router.push('/deals')} /> : null}
      {agent ? <TodayAgenda /> : null}
      {agent ? <TodayTodos rows={todos.rows} onChanged={() => void todos.reload()} onAdd={() => setCapturing('voice')} /> : null}
      <VoiceCaptureSheet mode={capturing} onClose={closeCapture} onSaved={() => void todos.reload()} />
      <Text variant="display">{t('common.appName')}</Text>
      <Text variant="bodyLarge" tone="body">
        {t('mobile.tagline')}
      </Text>

      <Card accessibilityLiveRegion="polite">
        {state.kind === 'loading' && <ActivityIndicator color={theme.colors.action} />}
        {state.kind === 'ok' && <Text>{t('mobile.connected', { app: state.health.app, version: state.health.version })}</Text>}
        {state.kind === 'error' && (
          <>
            <Text>{t('mobile.unreachable')}</Text>
            <Button label={t('common.tryAgain')} onPress={retry} />
          </>
        )}
      </Card>

      {__DEV__ ? <ListRow icon={Palette} tone="info" title="Component gallery" subtitle="Development builds only" onPress={() => router.push('/design')} /> : null}
    </Screen>
  );
}

const styles = StyleSheet.create({
  top: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  topActions: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  promptActions: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
});
