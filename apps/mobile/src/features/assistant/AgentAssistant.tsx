import { router, useFocusEffect } from 'expo-router';
import { CalendarClock, ExternalLink, FilePen, FileSearch, Mail, Megaphone, MessageSquare, Mic, SendHorizontal, ShieldCheck, ShieldQuestion, Sparkles, Square } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { Linking, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { allowAssistant, askAssistant, type AssistantChat, type AssistantHome, type AssistantMessage, itemRoute, loadAssistant, loadChat, type Starter } from '@/lib/assistant';
import { speechLanguage, useSpeech } from '@/lib/voice';
import { Button, Callout, Card, ChatBubble, EmptyState, Icon, IconButton, ListRow, Screen, SectionTitle, Text, TextField, TitleBar } from '@/ui';

const ICONS = { virtual_broker: ShieldQuestion, review_contract: FileSearch, prepare_contract: FilePen, write_ad: Megaphone, reply_emails: Mail, prep_appointment: CalendarClock } as const;

/**
 * Ask home and Ask anything for agents (agent screens 59 and 60; A12.3):
 * six starters, recent chats and the ask bar (typed or spoken). The
 * assistant picks the knowledge: the agent's day from their CRM, deals and
 * calendar (once allowed), an appointment's prep, a deal, a draft email or
 * text to open in Mail or Messages, or the virtual broker for policy.
 */
export function AgentAssistant() {
  const { t, i18n } = useTranslation();
  const [home, setHome] = useState<AssistantHome | null>(null);
  const [chat, setChat] = useState<AssistantChat | null>(null);
  const [question, setQuestion] = useState('');
  const [busy, setBusy] = useState(false);
  const [problem, setProblem] = useState<string | null>(null);

  useFocusEffect(
    useCallback(() => {
      void loadAssistant().then(setHome);
    }, []),
  );

  const send = async (text: string) => {
    if (text.trim().length < 2 || busy) return;
    setBusy(true);
    setProblem(null);
    try {
      setChat(await askAssistant(text.trim(), chat?.id ?? null));
      setQuestion('');
    } catch (e) {
      setProblem(e instanceof Error ? e.message : String(e));
    } finally {
      setBusy(false);
    }
  };
  const speech = useSpeech(speechLanguage(i18n.language), (words) => {
    if (words.length >= 2) void send(words);
  });
  const allow = async () => {
    await allowAssistant(true);
    setHome((h) => (h ? { ...h, permission: true } : h));
    const last = [...(chat?.messages ?? [])].reverse().find((m) => m.role === 'question');
    if (last) await send(last.body);
  };
  const start = (s: Starter) => {
    switch (s) {
      case 'virtual_broker':
        return router.push('/ask/broker');
      case 'review_contract':
        return router.push('/contracts');
      case 'write_ad':
        return router.push('/social/create');
      case 'reply_emails':
        return setQuestion(t('assistant.mobile.reply'));
      case 'prep_appointment':
        return void send(t('assistant.mobile.prep'));
      default:
        return undefined;
    }
  };

  return (
    <Screen
      footer={
        <View style={styles.composer}>
          <View style={styles.flex}>
            <TextField label={t('assistant.mobile.label')} value={speech.state === 'listening' ? speech.transcript : question} onChangeText={setQuestion} placeholder={t('assistant.mobile.placeholder')} maxLength={1000} returnKeyType="send" onSubmitEditing={() => void send(question)} />
          </View>
          <IconButton icon={speech.state === 'listening' ? Square : Mic} label={speech.state === 'listening' ? t('ask.stop') : t('ask.voice')} onPress={() => (speech.state === 'listening' ? speech.stop() : void speech.start())} />
          <IconButton icon={SendHorizontal} primary label={t('assistant.mobile.send')} onPress={() => void send(question)} />
        </View>
      }
    >
      <TitleBar title={t('assistant.mobile.title')} onBack={chat ? () => setChat(null) : () => router.back()} />
      {chat ? null : <Text tone="muted">{t('assistant.mobile.purpose')}</Text>}
      {speech.state === 'denied' ? <Callout tone="warning" title={t('ask.micDenied')} /> : null}

      {chat ? (
        chat.messages.map((m) => (m.role === 'question' ? <ChatBubble key={m.id} from="me">{m.body}</ChatBubble> : <Answer key={m.id} message={m} onAllow={() => void allow()} />))
      ) : (
        <>
          {home && !home.permission ? (
            <Card style={styles.gap}>
              <View style={styles.row}>
                <Icon icon={ShieldCheck} color="action" size={20} />
                <Text weight="semibold">{t('assistant.mobile.allowTitle')}</Text>
              </View>
              <Text tone="muted">{t('assistant.mobile.allowBody')}</Text>
              <Button label={t('assistant.mobile.allow')} variant="secondary" onPress={() => void allow()} />
            </Card>
          ) : null}
          {(home?.starters ?? []).map((s) => (
            <ListRow key={s} icon={ICONS[s]} tone={s === 'prepare_contract' ? 'neutral' : 'info'} title={t(`assistant.mobile.starters.${s}`)} subtitle={s === 'prepare_contract' ? t('assistant.mobile.soon') : undefined} onPress={s === 'prepare_contract' ? undefined : () => start(s)} />
          ))}
          <Button variant="secondary" icon={Sparkles} label={t('assistant.mobile.today')} fullWidth disabled={busy} onPress={() => void send(t('assistant.mobile.today'))} />
          {home && home.chats.length > 0 ? <SectionTitle>{t('assistant.mobile.recent')}</SectionTitle> : null}
          {home?.chats.slice(0, 10).map((c) => <ListRow key={c.id} icon={MessageSquare} title={c.title} onPress={() => void loadChat(c.id).then(setChat)} />)}
          {home && home.chats.length === 0 ? <EmptyState icon={<Icon icon={Sparkles} color="action" size={32} />} title={t('assistant.mobile.emptyTitle')} description={t('assistant.mobile.emptyBody')} /> : null}
        </>
      )}
      {busy ? <Text tone="muted">{t('assistant.mobile.asking')}</Text> : null}
      {problem ? <Callout tone="danger" title={problem} live /> : null}
    </Screen>
  );
}

function Answer({ message, onAllow }: { message: AssistantMessage; onAllow: () => void }) {
  const { t, i18n } = useTranslation();
  const time = (at: string) => (at.length === 10 ? new Intl.DateTimeFormat(i18n.language, { dateStyle: 'medium' }).format(new Date(`${at}T12:00:00`)) : new Intl.DateTimeFormat(i18n.language, { hour: 'numeric', minute: '2-digit' }).format(new Date(at)));

  return (
    <View style={styles.gap}>
      <ChatBubble from="assistant">{message.body}</ChatBubble>
      {message.kind === 'permission' ? <Button label={t('assistant.mobile.allow')} icon={ShieldCheck} onPress={onAllow} /> : null}
      {message.items.map((item, i) => {
        const route = itemRoute(item.link);
        return (
          <ListRow
            key={`${item.title}-${i}`}
            icon={message.kind === 'policy' ? ShieldQuestion : CalendarClock}
            title={item.title}
            subtitle={[item.detail, item.source, item.at ? time(item.at) : null].filter(Boolean).join(' · ')}
            onPress={route ? () => router.push(route) : undefined}
          />
        );
      })}
      {message.draft ? (
        <Card style={styles.gap}>
          <Text variant="caption" tone="muted">
            {message.draft.channel === 'email' ? message.draft.subject ?? '' : message.draft.to}
          </Text>
          <Text>{message.draft.body}</Text>
          <Button icon={ExternalLink} label={message.draft.channel === 'email' ? t('assistant.mobile.openMail') : t('assistant.mobile.openMessages')} onPress={() => void Linking.openURL(message.draft?.href ?? '')} />
        </Card>
      ) : null}
      {message.broker_question ? <Button variant="secondary" icon={ShieldQuestion} label={t('assistant.mobile.viewBroker')} onPress={() => router.push({ pathname: '/ask/broker/[id]', params: { id: message.broker_question ?? '' } })} /> : null}
    </View>
  );
}

const styles = StyleSheet.create((theme) => ({
  composer: { flexDirection: 'row', alignItems: 'flex-end', gap: theme.space.sm, paddingHorizontal: theme.m.gutter, paddingBottom: theme.space.lg },
  flex: { flex: 1 },
  gap: { gap: theme.space.sm },
  row: { flexDirection: 'row', alignItems: 'center', gap: theme.space.sm },
}));
