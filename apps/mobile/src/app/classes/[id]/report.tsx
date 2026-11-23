import type { TFunction } from 'i18next';
import { router, useFocusEffect, useLocalSearchParams } from 'expo-router';
import { Send } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { type ClassReport, decideCase, loadReport, sendReport } from '@/lib/classes';
import { Button, Callout, Card, Screen, SectionTitle, Text, TextField, TitleBar } from '@/ui';

type Student = ClassReport['students'][number];

const REASONS = { seat_time: 'classes.report.reasons.seat_time', checks: 'classes.report.reasons.checks', not_joined: 'classes.report.reasons.not_joined' } as const;

function facts(t: TFunction, s: Student): string {
  return [
    t('classes.report.present', { count: s.minutes_present }),
    t('classes.report.checks', { answered: s.checks_answered, total: s.checks_total }),
    ...s.reasons.map((r) => (r in REASONS ? t(REASONS[r as keyof typeof REASONS]) : r)),
  ].join(' · ');
}

function ReviewCase({ student, canDecide, onDecide }: { student: Student; canDecide: boolean; onDecide: (accept: boolean, reason: string) => void }) {
  const { t } = useTranslation();
  const [reason, setReason] = useState(student.decision_reason ?? '');

  return (
    <Card>
      <Text weight="semibold">{student.name}</Text>
      <Text variant="caption" tone="muted">
        {facts(t, student)}
      </Text>
      {student.decision ? <Text>{`${t(`classes.report.decided.${student.decision}`)}${student.decision_reason ? `: ${student.decision_reason}` : ''}`}</Text> : null}
      {canDecide ? (
        <>
          <TextField label={t('classes.report.reason')} value={reason} onChangeText={setReason} maxLength={500} multiline />
          <View style={styles.actions}>
            <Button label={t('classes.report.accept')} disabled={reason.trim().length < 3} onPress={() => onDecide(true, reason.trim())} />
            <Button variant="secondary" label={t('classes.report.reject')} disabled={reason.trim().length < 3} onPress={() => onDecide(false, reason.trim())} />
          </View>
        </>
      ) : null}
    </Card>
  );
}

/**
 * Class report (instructor i13, A10.2c): after a CE class, who joined,
 * earned CE and didn't by the approval's rules; borderline cases to
 * accept or reject with a reason; Confirm and send to school.
 */
export default function ClassReportScreen() {
  const { t } = useTranslation();
  const { id } = useLocalSearchParams<{ id: string }>();
  const [report, setReport] = useState<ClassReport | null>(null);
  const [problem, setProblem] = useState<string | null>(null);

  useFocusEffect(
    useCallback(() => {
      void loadReport(id).then(setReport);
    }, [id]),
  );

  const run = async (work: () => Promise<ClassReport>) => {
    try {
      setReport(await work());
      setProblem(null);
    } catch (e) {
      setProblem(e instanceof Error ? e.message : String(e));
    }
  };

  const review = report?.students.filter((s) => s.outcome === 'review') ?? [];
  const earned = report?.students.filter((s) => s.outcome === 'earned') ?? [];
  const didnt = report?.students.filter((s) => s.outcome === 'not_earned') ?? [];

  return (
    <Screen>
      <TitleBar title={t('classes.report.title')} onBack={() => router.back()} />
      {report ? (
        <>
          <Text weight="semibold">{`${report.class.title} · ${report.class.course}`}</Text>
          <Text tone="muted">{t('classes.report.rules', { percent: report.rules.presence_percent, minutes: report.rules.required_minutes, checks: report.rules.checks })}</Text>
          <Text>
            {[
              `${t('classes.report.counts.joined')}: ${report.counts.joined}`,
              `${t('classes.report.counts.earned')}: ${report.counts.earned}`,
              `${t('classes.report.counts.notEarned')}: ${report.counts.not_earned}`,
              `${t('classes.report.counts.review')}: ${report.counts.review}`,
            ].join(' · ')}
          </Text>
          {report.status === 'sent' ? (
            <Callout tone="success" title={t('classes.report.sent', { name: report.sent_by ?? '', date: report.sent_at ? new Date(report.sent_at).toLocaleString() : '' })} />
          ) : null}
          {problem ? <Callout tone="danger" title={problem} live /> : null}

          {review.length > 0 ? <SectionTitle>{t('classes.report.toReview')}</SectionTitle> : null}
          {review.map((s) => (
            <ReviewCase key={s.user_id} student={s} canDecide={report.can_decide} onDecide={(accept, reason) => void run(() => decideCase(id, s.user_id, accept, reason))} />
          ))}

          <SectionTitle>{t('classes.report.earned')}</SectionTitle>
          {earned.length === 0 ? <Text tone="muted">{t('classes.report.noneEarned')}</Text> : null}
          {earned.map((s) => (
            <Card key={s.user_id}>
              <Text weight="semibold">{s.name}</Text>
              <Text variant="caption" tone="muted">
                {facts(t, s)}
              </Text>
            </Card>
          ))}

          {didnt.length > 0 ? <SectionTitle>{t('classes.report.notEarned')}</SectionTitle> : null}
          {didnt.map((s) => (
            <Card key={s.user_id}>
              <Text weight="semibold">{s.name}</Text>
              <Text variant="caption" tone="muted">
                {facts(t, s)}
              </Text>
            </Card>
          ))}

          {report.status === 'draft' && report.can_decide ? (
            <>
              {report.counts.undecided > 0 ? <Text tone="muted">{t('classes.report.decideFirst', { count: report.counts.undecided })}</Text> : null}
              <Button icon={Send} label={t('classes.report.send')} disabled={!report.can_send} onPress={() => void run(() => sendReport(id))} />
            </>
          ) : null}
        </>
      ) : null}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  actions: { flexDirection: 'row', gap: theme.space.sm, flexWrap: 'wrap' },
}));
