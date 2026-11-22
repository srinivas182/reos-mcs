import { router, useLocalSearchParams } from 'expo-router';
import { BookOpen, Palette } from 'lucide-react-native';
import { useEffect, useState } from 'react';
import { ActivityIndicator, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';

import { TodayAgenda } from '@/features/calendar/TodayAgenda';
import { TodayTodos } from '@/features/todos/TodayTodos';
import { type CaptureMode, VoiceCaptureSheet } from '@/features/voice/VoiceCaptureSheet';
import { fetchHealth, type Health } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import { toolInitials, useConnections } from '@/lib/connections';
import { activeMembership } from '@/lib/roles';
import { timeAgo } from '@/lib/time';
import { useOpenTodos } from '@/lib/todos';
import { useWhatsNew } from '@/lib/whatsNew';
import { AskBar, Avatar, Button, Callout, Card, ListRow, Screen, SyncAlert, Text } from '@/ui';

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
  const [state, setState] = useState<State>({ kind: 'loading' });
  const [attempt, setAttempt] = useState(0);
  const { capture } = useLocalSearchParams<{ capture?: string }>();
  const [chosen, setCapturing] = useState<CaptureMode | null>(null);
  const capturing = chosen ?? (agent && (capture === 'voice' || capture === 'type') ? capture : null);
  const todos = useOpenTodos();
  const whatsNew = useWhatsNew();
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
        ) : undefined
      }
    >
      {/* Me opens from your initials, top right (journey screen 16). */}
      <View style={styles.top}>
        <Text variant="caption" weight="bold" tone="action">
          {t('mobile.eyebrow')}
        </Text>
        {user ? <Avatar name={user.name ?? user.email ?? user.phone ?? '?'} label={t('mobile.me.open')} onPress={() => router.push('/me')} /> : null}
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
      {/* Today's calendar for agents (B4.1); the rest of Today arrives with the agent sprints. */}
      {/* Learn (candidate screen 8, A5.3b): courses and search inside lessons; the Learn tab arrives with the tab bar. */}
      {learner ? <ListRow icon={BookOpen} tone="info" title={t('learn.title')} subtitle={t('learn.searchLabel')} onPress={() => router.push('/learn')} /> : null}
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
});
