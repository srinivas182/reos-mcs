import { router } from 'expo-router';
import { Users, Video } from 'lucide-react-native';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import type { Session } from '@/lib/sessions';
import { Card, Icon, Text } from '@/ui';

/** "Tue, Nov 24 · 3:00 – 3:45 PM" in the phone's language and time zone. */
export function sessionTime(session: Session, language: string): string {
  const start = new Date(session.starts_at);
  const day = start.toLocaleDateString(language, { weekday: 'short', month: 'short', day: 'numeric' });
  const time = (d: Date) => d.toLocaleTimeString(language, { hour: 'numeric', minute: '2-digit' });

  return `${day} · ${time(start)} – ${time(new Date(session.ends_at))}`;
}

export function SessionRow({ session, me }: { session: Session; me: number }) {
  const { t, i18n } = useTranslation();
  const others = session.people.filter((p) => p.id !== me).map((p) => p.name);
  const who = session.kind === 'live_class' ? t('sessions.people', { count: others.length }) : t('sessions.with', { names: others.join(', ') });
  const status = session.can_join ? t('sessions.ready') : t(`sessions.statuses.${session.status}`);

  return (
    <Card
      onPress={() => router.push({ pathname: '/sessions/[id]', params: { id: session.id } })}
      accessibilityLabel={[session.title, sessionTime(session, i18n.language), who, status].join('. ')}
    >
      <View style={styles.row}>
        <Icon icon={session.kind === 'live_class' ? Users : Video} color="action" />
        <View style={styles.text}>
          <Text weight="bold">{session.title}</Text>
          <Text tone="muted">{sessionTime(session, i18n.language)}</Text>
          <Text variant="caption" tone="muted">
            {who} · {status}
          </Text>
        </View>
      </View>
    </Card>
  );
}

const styles = StyleSheet.create((theme) => ({
  row: { flexDirection: 'row', gap: theme.space.md, alignItems: 'flex-start' },
  text: { flex: 1, gap: 2 },
}));
