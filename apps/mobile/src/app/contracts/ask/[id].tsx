import { router, useLocalSearchParams } from 'expo-router';
import { SendHorizontal, Share2 } from 'lucide-react-native';
import { useEffect, useState } from 'react';
import { Share, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { askContract, type ContractReview, loadReview } from '@/lib/contracts';
import { Button, Callout, Card, ChatBubble, IconButton, Screen, Text, TextField, TitleBar } from '@/ui';

/**
 * Ask about this contract (agent screen 66): answers from the contract's
 * own pages, naming them, and a note for the client to share.
 */
export default function ContractAskScreen() {
  const { t } = useTranslation();
  const { id } = useLocalSearchParams<{ id: string }>();
  const [review, setReview] = useState<ContractReview | null>(null);
  const [question, setQuestion] = useState('');
  const [busy, setBusy] = useState(false);
  const [problem, setProblem] = useState<string | null>(null);

  useEffect(() => {
    void loadReview(id).then(setReview);
  }, [id]);

  const ask = () => {
    if (question.trim().length < 3 || busy) return;
    setBusy(true);
    setProblem(null);
    void askContract(id, question.trim())
      .then((next) => {
        setReview(next);
        setQuestion('');
      })
      .catch((e: unknown) => setProblem(e instanceof Error ? e.message : String(e)))
      .finally(() => setBusy(false));
  };

  return (
    <Screen
      footer={
        <View style={styles.composer}>
          <View style={styles.flex}>
            <TextField label={t('contracts.web.askLabel')} value={question} onChangeText={setQuestion} placeholder={t('contracts.web.askPlaceholder')} maxLength={500} returnKeyType="send" onSubmitEditing={ask} />
          </View>
          <IconButton icon={SendHorizontal} primary label={t('contracts.web.ask')} onPress={ask} />
        </View>
      }
    >
      <TitleBar title={t('contracts.web.askTitle')} onBack={() => router.back()} />
      {review ? <Text tone="muted">{review.form_name ?? review.file_name}</Text> : null}
      <Text variant="caption" tone="muted">
        {t('contracts.web.askCost', { count: 1 })}
      </Text>
      {review?.questions.map((q) => (
        <View key={q.asked_at + q.question} style={styles.gap}>
          <ChatBubble from="me">{q.question}</ChatBubble>
          <ChatBubble from="assistant">{q.answer}</ChatBubble>
          <Card style={styles.gap}>
            <Text weight="semibold">{t('contracts.web.noteTitle')}</Text>
            <Text>{q.note}</Text>
            <Button
              variant="secondary"
              icon={Share2}
              label={t('contracts.web.share')}
              onPress={() => void Share.share({ message: q.note })}
            />
          </Card>
        </View>
      ))}
      {busy ? <Text tone="muted">{t('virtualBroker.asking')}</Text> : null}
      {problem ? <Callout tone="danger" title={problem} live /> : null}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  composer: { flexDirection: 'row', alignItems: 'flex-end', gap: theme.space.sm, paddingHorizontal: theme.m.gutter, paddingBottom: theme.space.lg },
  flex: { flex: 1 },
  gap: { gap: theme.space.sm },
}));
