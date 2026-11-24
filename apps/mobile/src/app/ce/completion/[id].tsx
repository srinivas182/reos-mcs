import { router, useLocalSearchParams } from 'expo-router';
import { CircleCheck, Circle, CircleDashed, CircleX, PlugZap, Share2 } from 'lucide-react-native';
import { Share } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { useCertificate } from '@/lib/ce';
import { Button, Card, ListRow, Screen, SectionTitle, Text, TitleBar } from '@/ui';
import type { Tone } from '@/ui/tones';

type State = 'done' | 'waiting' | 'pending' | 'rejected' | 'not_connected';

const ICON = { done: CircleCheck, waiting: Circle, pending: CircleDashed, rejected: CircleX, not_connected: PlugZap };
const TONE: Record<State, Tone> = { done: 'success', waiting: 'neutral', pending: 'info', rejected: 'danger', not_connected: 'neutral' };

const day = (iso: string, locale: string) => new Intl.DateTimeFormat(locale, { dateStyle: 'long' }).format(new Date(`${iso.slice(0, 10)}T12:00:00`));

/**
 * A CE certificate and its reporting (A11.3; agent screens 103 and 104):
 * the certificate once TREC has the completion, Share (with the broker),
 * and each step: completed, final passed, reported to TREC (reference),
 * the Texas REALTORS® member record and the certificate.
 */
export default function CompletionScreen() {
  const { t, i18n } = useTranslation();
  const { id } = useLocalSearchParams<{ id: string }>();
  const certificate = useCertificate(Number(id));

  if (certificate === null) return <Screen>{null}</Screen>;

  const reporting = certificate.reporting;
  const share = () =>
    void Share.share({
      message: t('ce.wallet.certificate.shareText', {
        name: certificate.holder,
        course: certificate.title,
        hours: certificate.hours,
        date: day(certificate.completed_on, i18n.language),
        reference: reporting?.reference ?? '',
      }),
    });

  return (
    <Screen>
      <TitleBar title={t('ce.wallet.certificate.title')} onBack={() => router.back()} />
      <Card style={styles.card}>
        <Text variant="title">{t('ce.wallet.certificate.heading')}</Text>
        <Text>
          {t('ce.wallet.certificate.holder')}: {certificate.holder}
        </Text>
        {certificate.licence ? (
          <Text>
            {t('ce.wallet.certificate.licence')}: {certificate.state_code} {certificate.licence}
          </Text>
        ) : null}
        <Text>
          {t('ce.wallet.certificate.course')}: {certificate.title}
        </Text>
        {certificate.course_number ? (
          <Text>
            {t('ce.wallet.certificate.courseNumber')}: {certificate.course_number}
          </Text>
        ) : null}
        <Text>
          {t('ce.wallet.certificate.hours')}: {certificate.hours}
        </Text>
        <Text>
          {t('ce.wallet.certificate.completed')}: {day(certificate.completed_on, i18n.language)}
        </Text>
        {certificate.provider ? (
          <Text>
            {t('ce.wallet.certificate.provider')}: {certificate.provider}
          </Text>
        ) : null}
        {reporting?.reference ? <Text>{t('ce.wallet.steps.reference', { reference: reporting.reference })}</Text> : null}
        {!certificate.issued ? <Text tone="muted">{reporting ? t('ce.wallet.certificate.notIssued') : t('ce.wallet.certificate.selfReported')}</Text> : null}
      </Card>
      {certificate.issued ? <Button icon={Share2} label={t('ce.wallet.certificate.share')} onPress={share} fullWidth /> : null}
      {reporting ? (
        <>
          <SectionTitle>{`${t('ce.wallet.steps.title')}: ${t(`ce.wallet.steps.status.${reporting.status}`)}`}</SectionTitle>
          {reporting.steps.map((step) => (
            <ListRow
              key={step.code}
              icon={ICON[step.state]}
              tone={TONE[step.state]}
              title={t(`ce.wallet.steps.codes.${step.code}`)}
              subtitle={[
                t(`ce.wallet.steps.states.${step.state}`),
                step.code === 'reported' && reporting.reference ? t('ce.wallet.steps.reference', { reference: reporting.reference }) : null,
                step.code === 'reported' && reporting.reason ? t('ce.wallet.steps.reason', { reason: reporting.reason }) : null,
              ]
                .filter(Boolean)
                .join(' · ')}
            />
          ))}
          <Text tone="muted">{t('ce.wallet.steps.memberNote')}</Text>
        </>
      ) : null}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  card: { gap: theme.space.xs },
}));
