import * as DocumentPicker from 'expo-document-picker';
import { Image } from 'expo-image';
import { router, useLocalSearchParams } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { useState } from 'react';
import { Pressable, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { useAuth } from '@/lib/auth';
import { type Attachment, changeable, deleteMessage, editMessage, type MessageItem, type PickedFile, readByAll, reportConversation, type ReportReason, sendMessage, setBlocked, useConversation } from '@/lib/messages';
import { Button, Callout, ChatBubble, OptionGroup, Screen, Sheet, SwitchRow, Text, TextField, TitleBar, useUndoToast } from '@/ui';

const REASONS: ReportReason[] = ['spam', 'harassment', 'inappropriate', 'other'];

/** Files in a message (B6.1b): images and PDFs once scanned, "Checking for viruses…" before. */
function Files({ files }: { files: Attachment[] }) {
  const { t } = useTranslation();

  return (
    <View style={styles.files}>
      {files.map((file) =>
        file.status === 'clean' && file.url && file.image ? (
          <Pressable key={file.id} accessibilityRole="imagebutton" accessibilityLabel={file.name} onPress={() => void WebBrowser.openBrowserAsync(file.url ?? '')}>
            <Image source={{ uri: file.url }} style={styles.image} contentFit="cover" accessibilityIgnoresInvertColors />
          </Pressable>
        ) : (
          <Pressable
            key={file.id}
            accessibilityRole="link"
            disabled={file.status !== 'clean' || file.url === null}
            onPress={() => void WebBrowser.openBrowserAsync(file.url ?? '')}
            style={styles.file}
          >
            <Text weight="medium" numberOfLines={1}>
              {file.name}
            </Text>
            <Text variant="caption" tone="muted">
              {file.status === 'pending' ? t('messages.files.checking') : file.status === 'infected' ? t('messages.files.infected') : t('messages.files.open')}
            </Text>
          </Pressable>
        ),
      )}
    </View>
  );
}

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
  const [files, setFiles] = useState<PickedFile[]>([]);
  const [reporting, setReporting] = useState(false);
  const [reason, setReason] = useState<ReportReason>('harassment');
  const [alsoBlock, setAlsoBlock] = useState(true);
  const undo = useUndoToast();
  const other = conversation?.kind === 'direct' ? conversation.people[0] : undefined;
  const blocked = (conversation?.blocked ?? null) !== null;

  const pick = async () => {
    const picked = await DocumentPicker.getDocumentAsync({ type: ['image/*', 'application/pdf'], multiple: true, copyToCacheDirectory: true });
    if (picked.canceled) return;
    setFiles([...files, ...picked.assets.map((a) => ({ uri: a.uri, name: a.name, type: a.mimeType ?? 'application/octet-stream' }))].slice(0, 5));
  };

  const report = async () => {
    const ok = await reportConversation(id, reason, null, null, other !== undefined && alsoBlock);
    setReporting(false);
    setError(!ok);
    if (ok) {
      undo({ message: t('messages.report.sent') });
      reload();
    }
  };

  const toggleBlock = async () => {
    if (!other) return;
    const ok = await setBlocked(other.id, conversation?.blocked !== 'by_me');
    if (ok) {
      undo({ message: conversation?.blocked === 'by_me' ? t('messages.unblocked') : t('messages.blocked') });
      reload();
    }
  };

  const submit = async () => {
    const text = body.trim();
    if (text === '' && (editing !== null || files.length === 0)) return;

    setBusy(true);
    const ok = editing ? await editMessage(editing.id, text) : await sendMessage(id, text, replyTo?.id ?? null, files);
    setBusy(false);
    setError(!ok);

    if (ok) {
      setBody('');
      setFiles([]);
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
      {files.length > 0 ? (
        <Text variant="caption" tone="muted">
          {files.map((f) => f.name).join(', ')}
        </Text>
      ) : null}
      <TextField label={t('messages.write')} value={body} onChangeText={setBody} multiline maxLength={4000} editable={!blocked} />
      <View style={styles.actions}>
        {!editing ? <Button variant="secondary" label={t('messages.files.add')} disabled={blocked} onPress={() => void pick()} /> : null}
        <Button
          label={editing ? t('messages.save') : t('messages.send')}
          loading={busy}
          disabled={blocked || (body.trim() === '' && (editing !== null || files.length === 0))}
          onPress={() => void submit()}
        />
      </View>
    </View>
  );

  return (
    <Screen footer={conversation ? composer : undefined}>
      <TitleBar title={conversation?.title ?? t('messages.title')} onBack={() => router.back()} />
      {failed || error ? <Callout tone="danger" title={t('messages.errors.send')} live /> : null}
      {conversation ? (
        <View style={styles.actions}>
          {other ? <Button variant="secondary" label={conversation.blocked === 'by_me' ? t('messages.unblock') : t('messages.block')} accessibilityLabel={conversation.blocked === 'by_me' ? t('messages.unblock') : t('messages.blockPerson', { name: other.name })} onPress={() => void toggleBlock()} /> : null}
          <Button variant="secondary" label={t('messages.report.action')} onPress={() => setReporting(true)} />
        </View>
      ) : null}
      {blocked ? <Callout tone="warning" title={conversation?.blocked === 'by_me' ? t('messages.blockedByMe', { name: other?.name ?? '' }) : t('messages.blockedByThem')} /> : null}
      <Sheet open={reporting} onClose={() => setReporting(false)} title={t('messages.report.title')}>
        <Text tone="muted">{t('messages.report.description')}</Text>
        <OptionGroup label={t('messages.report.why')} options={REASONS.map((r) => ({ value: r, label: t(`messages.report.reasons.${r}`) }))} value={reason} onChange={setReason} />
        {other ? <SwitchRow label={t('messages.report.alsoBlock')} value={alsoBlock} onValueChange={setAlsoBlock} /> : null}
        <Button variant="destructive" label={t('messages.report.send')} fullWidth onPress={() => void report()} />
      </Sheet>
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
              {message.deleted || message.body || message.reply_to ? (
                <ChatBubble from={mine ? 'me' : 'person'} senderName={sender}>
                  {message.deleted ? t('messages.deleted') : `${message.reply_to ? `↩ ${message.reply_to.sender ?? t('messages.formerMember')}: ${message.reply_to.deleted ? t('messages.deleted') : (message.reply_to.body ?? '')}\n` : ''}${message.body ?? ''}`}
                </ChatBubble>
              ) : null}
              {message.attachments.length > 0 ? <Files files={message.attachments} /> : null}
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
  actions: { flexDirection: 'row', flexWrap: 'wrap', gap: theme.space.sm },
  files: { gap: theme.space.sm },
  image: { width: 220, height: 160, borderRadius: theme.m.radius.card },
  file: { minHeight: 48, justifyContent: 'center', padding: theme.space.md, borderRadius: theme.m.radius.card, borderWidth: 1, borderColor: theme.colors.border, backgroundColor: theme.colors.surface },
}));
