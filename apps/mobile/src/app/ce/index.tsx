import { router } from 'expo-router';
import { BookOpen, CircleCheck, FileUp, GraduationCap, RefreshCw, ShieldCheck } from 'lucide-react-native';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { type CompletionStatus, useWallets, type Wallet } from '@/lib/ce';
import { Button, Card, Chip, EmptyState, Icon, ListRow, ProgressBar, Screen, SectionTitle, Text, TitleBar } from '@/ui';
import type { Tone } from '@/ui/tones';

const TONE: Record<CompletionStatus, Tone> = { reos_reported: 'info', self_reported: 'warning', on_record: 'success' };

const day = (iso: string, locale: string) => new Intl.DateTimeFormat(locale, { dateStyle: 'medium' }).format(new Date(`${iso.slice(0, 10)}T12:00:00`));

/**
 * The CE wallet (A11.2; agent screens 103 and 105, from Me → CE wallet):
 * for each licence, hours against the renewal with the required courses
 * and electives, TREC's own view, every completion and who reported it,
 * Add certificate, getting CE, and Renew your licence.
 */
export default function CeWalletScreen() {
  const { t } = useTranslation();
  const { wallets } = useWallets();

  if (wallets === null) return <Screen>{null}</Screen>;

  return (
    <Screen>
      <TitleBar title={t('ce.wallet.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('ce.wallet.description')}</Text>
      {wallets.length === 0 ? <EmptyState icon={<Icon icon={BookOpen} color="action" size={32} />} title={t('ce.wallet.emptyTitle')} description={t('ce.wallet.emptyBody')} /> : null}
      {wallets.map((wallet) => (
        <WalletSection key={wallet.licence.id} wallet={wallet} />
      ))}
    </Screen>
  );
}

function WalletSection({ wallet }: { wallet: Wallet }) {
  const { t, i18n } = useTranslation();
  const { ce, trec } = wallet;
  const status = ce.first_renewal ? trec.education : trec.mce;
  const percent = ce.hours_required > 0 ? (ce.hours_done / ce.hours_required) * 100 : 0;

  return (
    <View style={styles.section}>
      <SectionTitle>{t('ce.wallet.licence', { state: wallet.licence.state_name, number: wallet.licence.number })}</SectionTitle>
      <Card style={styles.card}>
        <Text variant="title">{t('ce.wallet.hours', { done: ce.hours_done, required: ce.hours_required })}</Text>
        <ProgressBar value={percent} label={t('ce.progress')} />
        {ce.due_on ? (
          <Text tone="muted">
            {t('ce.wallet.renewsOn', { date: day(ce.due_on, i18n.language) })}
            {ce.days_left !== null && ce.days_left >= 0 ? ` · ${t('ce.wallet.daysLeft', { count: ce.days_left })}` : ''}
          </Text>
        ) : null}
        {ce.parts.map((part) => (
          <Text key={part.code} tone={part.done ? 'muted' : 'body'}>
            {part.done ? '✓ ' : '○ '}
            {part.title} · {t('ce.partHours', { done: part.hours_done, hours: part.hours })}
          </Text>
        ))}
      </Card>
      <ListRow
        icon={ShieldCheck}
        tone={status === 'met' ? 'success' : 'info'}
        title={t('ce.wallet.trec.title')}
        subtitle={status ? t(ce.first_renewal ? `ce.wallet.trec.education.${status}` : `ce.wallet.trec.mce.${status}`) : t('ce.wallet.trec.unknown')}
      />
      {wallet.unlocked ? (
        <Button icon={GraduationCap} label={t('ce.wallet.takeCe')} onPress={() => router.push('/learn')} fullWidth />
      ) : wallet.buy ? (
        // Prices aren't shown in the app; Membership says how to get a plan.
        <Button icon={BookOpen} label={t('ce.wallet.buy')} onPress={() => router.push('/membership')} fullWidth />
      ) : null}
      <Button variant="secondary" icon={FileUp} label={t('ce.wallet.addCertificate')} onPress={() => router.push('/licences/certificate')} fullWidth />
      <ListRow
        icon={wallet.renewal.confirmed_at ? CircleCheck : RefreshCw}
        tone={wallet.renewal.ready ? 'success' : 'info'}
        title={t('ce.wallet.renew.title')}
        subtitle={wallet.renewal.ready ? t('ce.wallet.renew.ready') : t('ce.wallet.renew.notReady')}
        onPress={() => router.push({ pathname: '/ce/renew/[id]', params: { id: String(wallet.licence.id) } })}
      />
      <Text variant="title">{t('ce.wallet.completions')}</Text>
      {wallet.completions.length === 0 ? <EmptyState icon={<Icon icon={BookOpen} color="action" size={32} />} title={t('ce.wallet.emptyTitle')} description={t('ce.wallet.emptyBody')} /> : null}
      {wallet.completions.map((c) => (
        <ListRow
          key={c.id}
          icon={BookOpen}
          tone={TONE[c.status]}
          title={c.title}
          subtitle={[day(c.completed_on, i18n.language), c.provider, t('ce.wallet.hoursShort', { hours: c.hours }), c.reporting ? t(`ce.wallet.steps.status.${c.reporting.status}`) : null].filter(Boolean).join(' · ')}
          trailing={<Chip label={t(`ce.wallet.status.${c.status}`)} tone={TONE[c.status]} />}
          onPress={() => router.push({ pathname: '/ce/completion/[id]', params: { id: String(c.id) } })}
        />
      ))}
      {wallet.completions.some((c) => c.status === 'self_reported') ? <Text tone="muted">{t('ce.wallet.statusHint.self_reported')}</Text> : null}
    </View>
  );
}

const styles = StyleSheet.create((theme) => ({
  section: { gap: theme.space.sm },
  card: { gap: theme.space.xs },
}));
