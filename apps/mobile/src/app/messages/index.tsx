import { router } from 'expo-router';
import { MessageSquare, Plus } from 'lucide-react-native';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { useAuth } from '@/lib/auth';
import { useInbox } from '@/lib/messages';
import { timeAgo } from '@/lib/time';
import { Button, Card, EmptyState, Icon, Screen, Text, TitleBar } from '@/ui';

/**
 * Messages on the phone (B6.1), opened from Today: conversations latest
 * first with unread counts, live while the app is open; New message.
 */
export default function MessagesScreen() {
  const { t, i18n } = useTranslation();
  const { user } = useAuth();
  const { inbox } = useInbox(user?.id ?? null);

  return (
    <Screen footer={<Button label={t('messages.new')} icon={Plus} fullWidth onPress={() => router.push('/messages/new')} />}>
      <TitleBar title={t('messages.title')} onBack={() => router.back()} />
      {inbox !== null && inbox.conversations.length === 0 ? (
        <EmptyState icon={<Icon icon={MessageSquare} color="action" size={32} />} title={t('messages.empty')} description={t('messages.emptyBody')} />
      ) : null}
      <View style={styles.list}>
        {(inbox?.conversations ?? []).map((item) => {
          const last = item.last_message;
          const preview = last ? (last.deleted ? t('messages.deleted') : `${last.sender ? `${last.sender}: ` : ''}${last.body ?? ''}`) : '';

          return (
            <Card
              key={item.id}
              onPress={() => router.push({ pathname: '/messages/[id]', params: { id: item.id } })}
              accessibilityLabel={[item.title, preview, item.unread > 0 ? t('messages.unreadCount', { count: item.unread }) : null].filter(Boolean).join('. ')}
            >
              <View style={styles.row}>
                <Text weight={item.unread > 0 ? 'bold' : 'medium'} style={styles.title} numberOfLines={1}>
                  {item.title}
                </Text>
                {last ? (
                  <Text variant="caption" tone="muted">
                    {timeAgo(last.at, i18n.language)}
                  </Text>
                ) : null}
              </View>
              <View style={styles.row}>
                <Text tone="muted" numberOfLines={1} style={styles.title}>
                  {preview}
                </Text>
                {item.unread > 0 ? (
                  <Text variant="caption" weight="bold" tone="onAction" style={styles.badge}>
                    {String(item.unread)}
                  </Text>
                ) : null}
              </View>
            </Card>
          );
        })}
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  list: { gap: theme.space.md },
  row: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: theme.space.sm },
  title: { flex: 1 },
  badge: { backgroundColor: theme.colors.action, borderRadius: 999, paddingHorizontal: theme.space.sm, overflow: 'hidden' },
}));
