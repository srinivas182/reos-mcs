import { router, useLocalSearchParams } from 'expo-router';
import { CheckCircle2, Clock, ExternalLink, Handshake, PencilLine, Send } from 'lucide-react-native';
import { useEffect, useState } from 'react';
import { Linking, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { type BrokerQuestion, CONFIDENCE_TONE, loadQuestion, sendToBroker, sourceLabel } from '@/lib/virtualBroker';
import { Button, Callout, Card, ChatBubble, Chip, Icon, Screen, SectionTitle, Text, TitleBar } from '@/ui';

/**
 * One question to the virtual broker (agent screens 61 and 62): the answer
 * with its confidence and citations, "With your broker" and the expected
 * time, the broker's reply labelled Confirmed or Corrected, and the deal it
 * is about. Guidance, not legal advice.
 */
export default function BrokerQuestionScreen() {
  const { t, i18n } = useTranslation();
  const { id } = useLocalSearchParams<{ id: string }>();
  const [q, setQ] = useState<BrokerQuestion | null>(null);
  const [busy, setBusy] = useState(false);
  const [problem, setProblem] = useState<string | null>(null);

  useEffect(() => {
    void loadQuestion(id).then(setQ);
  }, [id]);

  const time = (value: string | null) => (value ? new Intl.DateTimeFormat(i18n.language, { weekday: 'short', hour: 'numeric', minute: '2-digit' }).format(new Date(value)) : '');
  const replied = q?.status === 'confirmed' || q?.status === 'corrected';

  return (
    <Screen>
        <TitleBar title={t('virtualBroker.title')} onBack={() => router.back()} />
        {q ? (
          <>
            <ChatBubble from="me">{q.question}</ChatBubble>
            {q.deal ? <Chip icon={Handshake} label={q.deal.property} onPress={() => router.push('/deals')} /> : null}

            {replied ? (
              <Card style={styles.gap}>
                <View style={styles.row}>
                  <Icon icon={q.status === 'confirmed' ? CheckCircle2 : PencilLine} color="success" size={20} />
                  <Text weight="semibold">{t('virtualBroker.fromBroker', { broker: q.broker ?? '' })}</Text>
                </View>
                <Chip tone="success" label={t(q.status === 'confirmed' ? 'virtualBroker.outcome.confirmed' : 'virtualBroker.outcome.corrected')} />
                <Text variant="bodyLarge">{q.broker_answer ?? ''}</Text>
              </Card>
            ) : null}

            <View style={styles.gap}>
              <View style={styles.row}>
                <Text variant="caption" weight="semibold" tone="muted">
                  {replied ? t('virtualBroker.aiAnswerWas') : t('virtualBroker.aiAnswer')}
                </Text>
                <Chip tone={CONFIDENCE_TONE[q.confidence]} label={t(`virtualBroker.confidence.${q.confidence}`)} />
              </View>
              <ChatBubble from="assistant">{q.answer}</ChatBubble>
            </View>

            {q.status === 'waiting' ? (
              <Callout tone="warning" title={t('virtualBroker.withBroker')} body={[q.due_at ? t('virtualBroker.expected', { time: time(q.due_at) }) : null, q.reason ? t(`virtualBroker.reasons.${q.reason}`) : null].filter(Boolean).join(' · ')} live />
            ) : null}

            {q.sources.length > 0 ? <SectionTitle>{t('virtualBroker.sources')}</SectionTitle> : null}
            {q.sources.map((s, i) => (
              <Card key={`${s.kind}-${i}`} style={styles.gap}>
                <Text weight="semibold">{sourceLabel(s, t)}</Text>
                {s.kind !== 'rule' && s.title ? <Text tone="muted">{s.title}</Text> : null}
                <Text>{s.excerpt}</Text>
                {s.url ? <Button variant="ghost" icon={ExternalLink} label={t('virtualBroker.source.open')} onPress={() => void Linking.openURL(s.url ?? '')} /> : null}
              </Card>
            ))}

            {problem ? <Callout tone="danger" title={problem} live /> : null}
            {q.status === 'answered' ? (
              <Button
                variant="secondary"
                icon={Send}
                label={t('virtualBroker.sendToBroker')}
                disabled={busy}
                onPress={() => {
                  setBusy(true);
                  void sendToBroker(q.id)
                    .then(setQ)
                    .catch((e: unknown) => setProblem(e instanceof Error ? e.message : String(e)))
                    .finally(() => setBusy(false));
                }}
              />
            ) : null}
            <View style={styles.row}>
              <Icon icon={Clock} color="textMuted" size={16} />
              <Text variant="caption" tone="muted">
                {t('virtualBroker.notLegalAdvice')}
              </Text>
            </View>
          </>
        ) : null}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  gap: { gap: theme.space.sm },
  row: { flexDirection: 'row', alignItems: 'center', flexWrap: 'wrap', gap: theme.space.xs },
}));
