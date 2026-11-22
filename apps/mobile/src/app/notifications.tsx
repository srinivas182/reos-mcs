import { router } from 'expo-router';
import { Bell } from 'lucide-react-native';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { type InboxItem, useNotificationInbox } from '@/lib/notifications';
import { timeAgo } from '@/lib/time';
import { Button, Card, EmptyState, Icon, Screen, Text, TitleBar } from '@/ui';

/**
 * Notifications on the phone (B6.2), from the bell on Today: everything
 * REOS told you, newest first, unread in bold; opening one marks it read.
 */
export default function NotificationsScreen() {
  const { t, i18n } = useTranslation();
  const { inbox, read } = useNotificationInbox();

  const open = (item: InboxItem) => {
    if (!item.read) void read(item.id);
    // Links inside REOS open their screen when the app has one; the rest stay in the inbox.
    if (item.url?.includes('/messages')) router.push('/messages');
  };

  return (
    <Screen footer={inbox && inbox.unread > 0 ? <Button variant="secondary" label={t('notifications.readAll')} fullWidth onPress={() => void read()} /> : undefined}>
      <TitleBar title={t('notifications.title')} onBack={() => router.back()} />
      {inbox !== null && inbox.items.length === 0 ? (
        <EmptyState icon={<Icon icon={Bell} color="action" size={32} />} title={t('notifications.empty')} description={t('notifications.emptyBody')} />
      ) : null}
      <View style={styles.list}>
        {(inbox?.items ?? []).map((item) => (
          <Card key={item.id} onPress={() => open(item)} accessibilityLabel={[item.title, item.body, item.read ? null : t('notifications.unread')].filter(Boolean).join('. ')}>
            <View style={styles.row}>
              <Text weight={item.read ? 'medium' : 'bold'} style={styles.title}>
                {item.title}
              </Text>
              <Text variant="caption" tone="muted">
                {timeAgo(item.at, i18n.language)}
              </Text>
            </View>
            {item.body ? <Text tone="muted">{item.body}</Text> : null}
          </Card>
        ))}
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  list: { gap: theme.space.md },
  row: { flexDirection: 'row', alignItems: 'flex-start', justifyContent: 'space-between', gap: theme.space.sm },
  title: { flex: 1 },
}));
