import { router, useFocusEffect } from 'expo-router';
import { MessageSquareQuote, Users } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { DIMENSIONS, type Dimension, type FeedbackItem, type FeedbackView, loadFeedback, rateAgent, reportFeedback, respondToFeedback, showFeedbackPublicly, type ToRate } from '@/lib/feedback';
import { Button, Callout, Card, EmptyState, Icon, PillGroup, Screen, SectionTitle, SwitchRow, Text, TextField, TitleBar, useUndoToast } from '@/ui';

type Star = '1' | '2' | '3' | '4' | '5';

function RateCard({ row, busy, onSend }: { row: ToRate; busy: boolean; onSend: (row: ToRate, scores: Record<Dimension, number>, comment: string) => void }) {
  const { t } = useTranslation();
  const [scores, setScores] = useState<Record<Dimension, number>>({ communication: 0, responsiveness: 0, professionalism: 0 });
  const [comment, setComment] = useState('');
  const stars = (['1', '2', '3', '4', '5'] as const).map((n) => ({ value: n, label: t('feedback.stars', { count: Number(n) }) }));

  return (
    <Card>
      <Text weight="semibold">{t('feedback.rate', { name: row.agent.name })}</Text>
      <Text tone="muted">{row.property}</Text>
      {DIMENSIONS.map((d) => (
        <PillGroup<Star> key={d} label={t(`feedback.dimensions.${d}`)} value={(scores[d] > 0 ? String(scores[d]) : '') as Star} options={stars} onChange={(v) => setScores({ ...scores, [d]: Number(v) })} />
      ))}
      <TextField label={t('feedback.comment')} hint={t('feedback.commentHint')} value={comment} maxLength={1000} multiline onChangeText={setComment} />
      <Button label={t('feedback.send')} loading={busy} disabled={DIMENSIONS.some((d) => scores[d] === 0)} onPress={() => onSend(row, scores, comment)} />
    </Card>
  );
}

function ItemCard({ item, busy, onRespond, onReport }: { item: FeedbackItem; busy: boolean; onRespond: (item: FeedbackItem, text: string) => void; onReport: (item: FeedbackItem, reason: string) => void }) {
  const { t, i18n } = useTranslation();
  const [mode, setMode] = useState<'none' | 'respond' | 'report'>('none');
  const [text, setText] = useState(item.response ?? '');
  const month = new Intl.DateTimeFormat(i18n.language, { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(new Date(`${item.month}-15T12:00:00Z`));

  return (
    <Card>
      <Text tone="muted">{item.reported ? `${month} · ${t('feedback.reportedBadge')}` : month}</Text>
      <Text>{DIMENSIONS.map((d) => t('feedback.scoreLine', { label: t(`feedback.dimensions.${d}`), score: item[d] })).join(' · ')}</Text>
      {item.comment ? <Text>{item.comment}</Text> : null}
      {item.response && mode === 'none' ? <Text tone="muted">{`${t('feedback.yourResponse')}: ${item.response}`}</Text> : null}
      {mode !== 'none' ? (
        <>
          <TextField label={mode === 'respond' ? t('feedback.yourResponse') : t('feedback.reportReason')} value={text} maxLength={mode === 'respond' ? 500 : 300} multiline onChangeText={setText} />
          <Button
            label={mode === 'respond' ? t('feedback.saveResponse') : t('feedback.reportSend')}
            variant={mode === 'respond' ? 'primary' : 'destructive'}
            loading={busy}
            disabled={text.trim().length < 2}
            onPress={() => {
              if (mode === 'respond') onRespond(item, text.trim());
              else onReport(item, text.trim());
              setMode('none');
            }}
          />
        </>
      ) : (
        <>
          <Button label={t('feedback.respond')} variant="secondary" onPress={() => setMode('respond')} />
          {!item.reported ? (
            <Button
              label={t('feedback.report')}
              variant="ghost"
              onPress={() => {
                setText('');
                setMode('report');
              }}
            />
          ) : null}
        </>
      )}
    </Card>
  );
}

/**
 * Feedback from agents (agent screen 125; A13.3b): rate the other side's
 * agent after a verified closing; your own feedback shows once three agents
 * have rated you, with a tip, responses and reports, and you choose whether
 * the average shows on your agent page.
 */
export default function FeedbackScreen() {
  const { t } = useTranslation();
  const toast = useUndoToast();
  const [view, setView] = useState<FeedbackView | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useFocusEffect(
    useCallback(() => {
      void loadFeedback().then(setView);
    }, []),
  );

  const run = async (work: () => Promise<FeedbackView>, after?: () => void) => {
    setBusy(true);
    setError(null);
    try {
      setView(await work());
      after?.();
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setBusy(false);
    }
  };
  const mine = view?.mine;

  return (
    <Screen>
      <TitleBar title={t('feedback.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('feedback.purpose')}</Text>
      {error ? <Callout tone="danger" title={error} /> : null}

      <SectionTitle>{t('feedback.toRate')}</SectionTitle>
      {view && view.to_rate.length === 0 ? <EmptyState icon={<Icon icon={Users} color="action" size={32} />} title={t('feedback.noneToRate')} description={t('feedback.noneToRateHint')} /> : null}
      {view?.to_rate.map((row) => (
        <RateCard key={`${row.deal}-${row.agent.id}`} row={row} busy={busy} onSend={(r, scores, comment) => void run(() => rateAgent(r, scores, comment), () => toast({ message: t('feedback.sent') }))} />
      ))}

      <SectionTitle>{t('feedback.yours')}</SectionTitle>
      {mine && !mine.shown ? (
        <EmptyState icon={<Icon icon={MessageSquareQuote} color="action" size={32} />} title={mine.count === 0 ? t('feedback.none') : t('feedback.waiting', { count: mine.count, needed: mine.needed })} description={t('feedback.toRateHint')} />
      ) : null}
      {mine?.average ? (
        <Card>
          <Text weight="semibold">{t('feedback.average', { average: mine.average.overall.toFixed(1), count: mine.count })}</Text>
          <Text>{DIMENSIONS.map((d) => t('feedback.scoreLine', { label: t(`feedback.dimensions.${d}`), score: mine.average?.[d].toFixed(1) })).join(' · ')}</Text>
        </Card>
      ) : null}
      {mine?.tip ? <Callout tone="info" title={`${t('feedback.tip')}: ${t(`feedback.dimensions.${mine.tip.dimension}`)}`} body={mine.tip.text} /> : null}
      {mine?.items.map((item) => (
        <ItemCard
          key={item.id}
          item={item}
          busy={busy}
          onRespond={(it, text) => void run(() => respondToFeedback(it.id, text))}
          onReport={(it, reason) => void run(() => reportFeedback(it.id, reason), () => toast({ message: t('feedback.reported'), onUndo: () => void reportFeedback(it.id, null).then(setView) }))}
        />
      ))}
      {mine ? <SwitchRow label={t('feedback.public')} description={t('feedback.publicHint')} value={mine.public} disabled={busy} onValueChange={(v) => void run(() => showFeedbackPublicly(v))} /> : null}
    </Screen>
  );
}
