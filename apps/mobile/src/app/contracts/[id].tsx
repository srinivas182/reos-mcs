import { router, useLocalSearchParams } from 'expo-router';
import { AlertTriangle, CalendarPlus, CheckCircle2, Circle, Loader, MessageCircleQuestion, Send } from 'lucide-react-native';
import { useEffect, useState } from 'react';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { addDeadlines, type ContractReview, loadReview, sendReviewToBroker, STEPS } from '@/lib/contracts';
import { Button, Callout, Card, Chip, Icon, SectionTitle, Screen, Text, TitleBar } from '@/ui';

/**
 * Reviewing (agent screen 64) and the result (screen 65): the steps while
 * it reads (checked every few seconds; a notification comes when done),
 * then findings ranked High and Check with pages and dates, deadlines to
 * add to the calendar, key terms with what changed, Send to broker and
 * Ask about it. Not legal advice.
 */
export default function ContractReviewScreen() {
  const { t, i18n } = useTranslation();
  const { id } = useLocalSearchParams<{ id: string }>();
  const [review, setReview] = useState<ContractReview | null>(null);
  const [busy, setBusy] = useState(false);
  const [problem, setProblem] = useState<string | null>(null);

  useEffect(() => {
    void loadReview(id).then(setReview);
  }, [id]);
  useEffect(() => {
    if (review?.status !== 'reviewing') return undefined;
    const timer = setInterval(() => void loadReview(id).then(setReview), 3000);
    return () => clearInterval(timer);
  }, [id, review?.status]);

  const act = (run: () => Promise<ContractReview>) => {
    setBusy(true);
    setProblem(null);
    void run()
      .then(setReview)
      .catch((e: unknown) => setProblem(e instanceof Error ? e.message : String(e)))
      .finally(() => setBusy(false));
  };
  const day = (value: string) => new Intl.DateTimeFormat(i18n.language, { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' }).format(new Date(`${value}T12:00:00`));
  const at = review ? (review.step === 'done' ? STEPS.length : STEPS.indexOf(review.step as (typeof STEPS)[number])) : 0;

  return (
    <Screen>
      <TitleBar title={t('contracts.web.title')} onBack={() => router.back()} />
      {review ? (
        <>
          <Text variant="bodyLarge" weight="semibold">
            {review.form_name ?? review.file_name}
          </Text>
          {review.deal ? <Text tone="muted">{t('contracts.web.onDeal', { property: review.deal.property })}</Text> : null}

          {review.status === 'reviewing' ? (
            <Card style={styles.gap}>
              <Text>{t('contracts.web.reviewing')}</Text>
              {STEPS.map((s, i) => (
                <View key={s} style={styles.row}>
                  <Icon icon={i < at ? CheckCircle2 : i === at ? Loader : Circle} color={i < at ? 'success' : i === at ? 'action' : 'textMuted'} size={20} />
                  <Text weight={i === at ? 'semibold' : 'regular'}>{t(`contracts.web.steps.${s}`)}</Text>
                </View>
              ))}
            </Card>
          ) : null}
          {review.status === 'failed' ? <Callout tone="danger" title={review.error ?? t('contracts.web.status.failed')} live /> : null}

          {review.status === 'done' ? (
            <>
              <SectionTitle>{t('contracts.web.findings')}</SectionTitle>
              {review.findings.length === 0 ? <Callout tone="success" title={t('contracts.web.noFindings')} body={t('contracts.web.noFindingsBody')} /> : null}
              {review.findings.map((f, i) => (
                <Card key={`${f.code}-${i}`} style={styles.gap}>
                  <View style={styles.row}>
                    <Icon icon={AlertTriangle} color={f.severity === 'high' ? 'danger' : 'warning'} size={20} />
                    <Chip tone={f.severity === 'high' ? 'danger' : 'warning'} label={t(f.severity === 'high' ? 'contracts.severity.high' : 'contracts.severity.check')} />
                  </View>
                  <Text weight="semibold">{f.title}</Text>
                  <Text>{f.detail}</Text>
                  <Text variant="caption" tone="muted">
                    {[f.date ? day(f.date) : null, f.page !== null ? t('contracts.web.pageShort', { page: f.page }) : null, f.citation].filter(Boolean).join(' · ')}
                  </Text>
                </Card>
              ))}

              {review.deadlines.length > 0 ? <SectionTitle>{t('contracts.web.deadlines')}</SectionTitle> : null}
              {review.deadlines.map((d) => (
                <View key={d.code} style={styles.line}>
                  <View style={styles.flex}>
                    <Text weight="semibold">{d.label}</Text>
                    <Text variant="caption" tone="muted">
                      {d.basis}
                    </Text>
                  </View>
                  <Text>{day(d.due_on)}</Text>
                </View>
              ))}
              {review.deadlines.length > 0 ? (
                <Button icon={review.deadlines_added ? CheckCircle2 : CalendarPlus} label={review.deadlines_added ? t('contracts.web.deadlinesAdded') : t('contracts.web.addDeadlines')} disabled={busy || review.deadlines_added} fullWidth onPress={() => act(() => addDeadlines(review.id))} />
              ) : null}

              <SectionTitle>{t('contracts.web.keyTerms')}</SectionTitle>
              {review.terms.map((term) => (
                <View key={term.code} style={styles.line}>
                  <Text tone="muted" style={styles.flex}>
                    {term.label}
                  </Text>
                  <View style={styles.value}>
                    <Text weight="semibold">{term.value}</Text>
                    {term.changed ? <Chip tone="info" label={`${t('contracts.web.changed')} · ${t('contracts.web.was', { value: term.previous ?? '—' })}`} /> : null}
                  </View>
                </View>
              ))}

              {problem ? <Callout tone="danger" title={problem} live /> : null}
              <Button variant="secondary" icon={Send} label={review.broker_question ? t('contracts.web.sentToBroker') : t('contracts.web.sendToBroker')} disabled={busy || review.broker_question !== null} fullWidth onPress={() => act(() => sendReviewToBroker(review.id))} />
              <Button variant="secondary" icon={MessageCircleQuestion} label={t('contracts.web.askTitle')} fullWidth onPress={() => router.push({ pathname: '/contracts/ask/[id]', params: { id: review.id } })} />
              <Text variant="caption" tone="muted">
                {t('contracts.web.notLegalAdvice')}
              </Text>
            </>
          ) : null}
        </>
      ) : null}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  gap: { gap: theme.space.sm },
  row: { flexDirection: 'row', alignItems: 'center', gap: theme.space.sm },
  line: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: theme.space.md, paddingVertical: theme.space.sm },
  flex: { flex: 1 },
  value: { alignItems: 'flex-end', gap: theme.space.xs, flexShrink: 1 },
}));
