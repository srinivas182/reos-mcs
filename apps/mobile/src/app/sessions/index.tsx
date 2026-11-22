import { router } from 'expo-router';
import { CalendarPlus, Video } from 'lucide-react-native';
import { useState } from 'react';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { useAuth } from '@/lib/auth';
import { SessionRow } from '@/features/sessions/SessionRow';
import { useSessions } from '@/lib/sessions';
import { Button, EmptyState, Eyebrow, Icon, Screen, Text, TitleBar } from '@/ui';

/**
 * Sessions on the phone (B6.3), from Today: coming up, then the last month
 * with their notes. Instructors and mentors schedule here or on the web.
 */
export default function SessionsScreen() {
  const { t } = useTranslation();
  const { user } = useAuth();
  const { list } = useSessions();
  const me = user?.id ?? 0;
  // When the screen opened; the list reloads each time it comes into view.
  const [now] = useState(() => Date.now());
  const sessions = list?.sessions ?? [];
  const coming = sessions.filter((s) => (s.status === 'scheduled' || s.status === 'live') && new Date(s.ends_at).getTime() > now);
  const past = sessions.filter((s) => !coming.includes(s)).reverse();

  return (
    <Screen footer={list?.can_host ? <Button label={t('sessions.schedule.open')} icon={CalendarPlus} fullWidth onPress={() => router.push('/sessions/new')} /> : undefined}>
      <TitleBar title={t('sessions.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('sessions.purpose')}</Text>
      <Eyebrow>{t('sessions.coming')}</Eyebrow>
      {list !== null && coming.length === 0 ? (
        <EmptyState icon={<Icon icon={Video} color="action" size={32} />} title={t('sessions.empty')} description={list.can_host ? t('sessions.emptyHost') : t('sessions.emptyGuest')} />
      ) : null}
      <View style={styles.list}>
        {coming.map((s) => (
          <SessionRow key={s.id} session={s} me={me} />
        ))}
      </View>
      {past.length > 0 ? (
        <>
          <Eyebrow>{t('sessions.past')}</Eyebrow>
          <View style={styles.list}>
            {past.map((s) => (
              <SessionRow key={s.id} session={s} me={me} />
            ))}
          </View>
        </>
      ) : null}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  list: { gap: theme.space.md },
}));
