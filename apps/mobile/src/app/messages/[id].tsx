import { router, useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { Pressable, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { useAuth } from '@/lib/auth';
import { changeable, deleteMessage, editMessage, type MessageItem, readByAll, sendMessage, useConversation } from '@/lib/messages';
import { Button, Callout, ChatBubble, Screen, Text, TextField, TitleBar } from '@/ui';

/**
 * One conversation on the phone (B6.1): messages oldest first, live; reply,
 * and edit or delete your own for 5 minutes; "Read" under yours once
 * everyone has seen it. Opening it marks it read.
 */
export default function ConversationScreen() {
  const { t, i18n } = useTranslation();
  const { id } = useLocalSearchParams<{ id: string }>();
  const { user } = useAuth();
  const { conversation, failed, reload } = useConversation(id);
  const [body, setBody] = useState('');
  const [replyTo, setReplyTo] = useState<MessageItem | null>(null);
  const [editing, setEditing] = useState<MessageItem | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(false);

  const submit = async () => {
    const text = body.trim();
    if (text === '') return;

    setBusy(true);
    const ok = editing ? await editMessage(editing.id, text) : await sendMessage(id, text, replyTo?.id ?? null);
    setBusy(false);
    setError(!ok);

    if (ok) {
      setBody('');
      setReplyTo(null);
      setEditing(null);
      reload();
    }
  };

  const time = (iso: string) => new Date(iso).toLocaleTimeString(i18n.language, { hour: 'numeric', minute: '2-digit' });

  const composer = (
    <View style={styles.composer}>
      {replyTo || editing ? (
        <View style={styles.context}>
          <Text variant="caption" tone="muted" style={styles.flex}>
            {editing ? t('messages.editing') : t('messages.replyingTo', { name: replyTo?.sender?.name ?? t('messages.formerMember') })}
          </Text>
          <Button
            variant="ghost"
            label={t('messages.cancel')}
            onPress={() => {
              setReplyTo(null);
              if (editing) {
                setEditing(null);
                setBody('');
              }
            }}
          />
        </View>
      ) : null}
      <TextField label={t('messages.write')} value={body} onChangeText={setBody} multiline maxLength={4000} />
      <Button label={editing ? t('messages.save') : t('messages.send')} loading={busy} disabled={body.trim() === ''} fullWidth onPress={() => void submit()} />
    </View>
  );

  return (
    <Screen footer={conversation ? composer : undefined}>
      <TitleBar title={conversation?.title ?? t('messages.title')} onBack={() => router.back()} />
      {failed || error ? <Callout tone="danger" title={t('messages.errors.send')} live /> : null}
      <View style={styles.list} accessibilityLiveRegion="polite">
        {(conversation?.messages ?? []).map((message) => {
          const mine = message.sender?.id === user?.id;
          const sender = message.sender?.name ?? t('messages.formerMember');

          return (
            <View key={message.id} style={[styles.item, mine ? styles.mine : styles.theirs]}>
              {!mine && conversation?.kind === 'group' ? (
                <Text variant="caption" tone="muted">
                  {sender}
                </Text>
              ) : null}
              <ChatBubble from={mine ? 'me' : 'person'} senderName={sender}>
                {message.deleted ? t('messages.deleted') : `${message.reply_to ? `↩ ${message.reply_to.sender ?? t('messages.formerMember')}: ${message.reply_to.deleted ? t('messages.deleted') : (message.reply_to.body ?? '')}\n` : ''}${message.body ?? ''}`}
              </ChatBubble>
              <View style={styles.meta}>
                <Text variant="caption" tone="muted">
                  {[time(message.created_at), message.edited && !message.deleted ? t('messages.edited') : null, mine && conversation && readByAll(conversation, message) ? t('messages.read') : null].filter(Boolean).join(' · ')}
                </Text>
                {!message.deleted ? (
                  <Pressable accessibilityRole="button" accessibilityLabel={t('messages.replyTo', { name: sender })} onPress={() => setReplyTo(message)} style={styles.action}>
                    <Text variant="caption" tone="action">
                      {t('messages.reply')}
                    </Text>
                  </Pressable>
                ) : null}
                {mine && changeable(message) ? (
                  <>
                    <Pressable
                      accessibilityRole="button"
                      onPress={() => {
                        setReplyTo(null);
                        setEditing(message);
                        setBody(message.body ?? '');
                      }}
                      style={styles.action}
                    >
                      <Text variant="caption" tone="action">
                        {t('messages.edit')}
                      </Text>
                    </Pressable>
                    <Pressable accessibilityRole="button" onPress={() => void deleteMessage(message.id).then(reload)} style={styles.action}>
                      <Text variant="caption" tone="danger">
                        {t('messages.deleteForEveryone')}
                      </Text>
                    </Pressable>
                  </>
                ) : null}
              </View>
            </View>
          );
        })}
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  list: { gap: theme.space.lg },
  item: { gap: theme.space.xs },
  mine: { alignItems: 'flex-end' },
  theirs: { alignItems: 'flex-start' },
  meta: { flexDirection: 'row', flexWrap: 'wrap', alignItems: 'center', gap: theme.space.sm },
  action: { minHeight: 48, justifyContent: 'center', paddingHorizontal: theme.space.xs },
  composer: { gap: theme.space.sm },
  context: { flexDirection: 'row', alignItems: 'center', gap: theme.space.sm },
  flex: { flex: 1 },
}));
