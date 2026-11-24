import { router, useFocusEffect } from 'expo-router';
import { BookOpen, Mic, SendHorizontal, ShieldQuestion, Square } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { askVirtualBroker, loadVirtualBroker, STATUS_TONE, type VirtualBroker } from '@/lib/virtualBroker';
import { speechLanguage, useSpeech } from '@/lib/voice';
import { Callout, Chip, EmptyState, Icon, IconButton, ListRow, Screen, SectionTitle, Text, TextField, TitleBar } from '@/ui';

/**
 * Virtual broker (agent screen 61; A12.1): the brokerage's policies and the
 * Texas rules, any time. Typed or spoken questions; each answer opens with
 * its confidence and sources. Money and legal questions, and anything the
 * sources don't answer clearly, go to the broker automatically.
 */
export default function VirtualBrokerScreen() {
  const { t, i18n } = useTranslation();
  const [broker, setBroker] = useState<VirtualBroker | null | undefined>(undefined);
  const [question, setQuestion] = useState('');
  const [busy, setBusy] = useState(false);
  const [problem, setProblem] = useState<string | null>(null);

  useFocusEffect(
    useCallback(() => {
      void loadVirtualBroker().then(setBroker);
    }, []),
  );

  const ask = async (text: string) => {
    if (text.trim().length < 3 || busy) {
      return;
    }
    setBusy(true);
    setProblem(null);
    try {
      const asked = await askVirtualBroker(text.trim());
      setQuestion('');
      router.push({ pathname: '/ask/broker/[id]', params: { id: asked.id } });
    } catch (e) {
      setProblem(e instanceof Error ? e.message : String(e));
    } finally {
      setBusy(false);
    }
  };
  const speech = useSpeech(speechLanguage(i18n.language), (words) => {
    if (words.length >= 3) {
      void ask(words);
    }
  });

  return (
    <Screen
      footer={
        broker ? (
          <View style={styles.composer}>
            <View style={styles.flex}>
              <TextField label={t('virtualBroker.askLabel', { brokerage: broker.brokerage })} value={speech.state === 'listening' ? speech.transcript : question} onChangeText={setQuestion} placeholder={t('virtualBroker.placeholder')} maxLength={1000} returnKeyType="send" onSubmitEditing={() => void ask(question)} />
            </View>
            <IconButton icon={speech.state === 'listening' ? Square : Mic} label={speech.state === 'listening' ? t('ask.stop') : t('ask.voice')} onPress={() => (speech.state === 'listening' ? speech.stop() : void speech.start())} />
            <IconButton icon={SendHorizontal} primary label={t('virtualBroker.ask')} onPress={() => void ask(question)} />
          </View>
        ) : undefined
      }
    >
      <TitleBar title={t('virtualBroker.title')} onBack={() => router.back()} />
      {broker === null ? (
        <EmptyState icon={<Icon icon={ShieldQuestion} color="action" size={32} />} title={t('virtualBroker.mobile.noBrokerage')} description={t('virtualBroker.mobile.noBrokerageBody')} />
      ) : broker ? (
        <>
          <Text tone="muted">{t('virtualBroker.purpose', { brokerage: broker.brokerage })}</Text>
          {broker.manual ? (
            <Chip tone="info" icon={BookOpen} label={t('virtualBroker.mobile.trained', { brokerage: broker.brokerage })} />
          ) : (
            <Callout tone="info" title={t('virtualBroker.noManual', { brokerage: broker.brokerage })} />
          )}
          <Text variant="caption" tone="muted">
            {t('virtualBroker.free')}
          </Text>
          {speech.state === 'denied' ? <Callout tone="warning" title={t('ask.micDenied')} /> : null}
          {busy ? <Text tone="muted">{t('virtualBroker.asking')}</Text> : null}
          {problem ? <Callout tone="danger" title={problem} live /> : null}
          <SectionTitle>{t('virtualBroker.conversations')}</SectionTitle>
          {broker.questions.length === 0 ? (
            <EmptyState icon={<Icon icon={ShieldQuestion} color="action" size={32} />} title={t('virtualBroker.empty')} description={t('virtualBroker.emptyBody')} />
          ) : (
            broker.questions.map((q) => (
              <ListRow
                key={q.id}
                icon={ShieldQuestion}
                tone={STATUS_TONE[q.status]}
                title={q.question}
                subtitle={t(`virtualBroker.status.${q.status}`)}
                onPress={() => router.push({ pathname: '/ask/broker/[id]', params: { id: q.id } })}
              />
            ))
          )}
        </>
      ) : null}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  composer: { flexDirection: 'row', alignItems: 'flex-end', gap: theme.space.sm, paddingHorizontal: theme.m.gutter, paddingBottom: theme.space.lg },
  flex: { flex: 1 },
}));
