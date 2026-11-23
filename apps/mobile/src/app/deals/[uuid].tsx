import { router, useLocalSearchParams } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { CalendarClock, CheckCircle2, Circle, CircleAlert, CircleX, Clock, ExternalLink, FileText, Lock, Mail, MailOpen } from 'lucide-react-native';
import { ActivityIndicator, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';

import { loadDeal, money, TOOL_NAMES, useLoaded } from '@/lib/deals';
import { Button, Callout, Card, Icon, Screen, SectionTitle, Text, TitleBar } from '@/ui';

const SIGNER_ICONS = { waiting: Circle, sent: Mail, delivered: MailOpen, signed: CheckCircle2, declined: CircleX } as const;

/**
 * One deal on the phone (B9.1): stage, price and commission, key dates,
 * parties and documents. A deal from a tool opens there; changes are made
 * in the tool (decision 3). B9.3: each envelope sent from the deal with
 * its signers' progress, and the signed copies filed in REOS (sending
 * happens on the website, where DocuSign's sender view has room).
 */
export default function DealScreen() {
  const { t, i18n } = useTranslation();
  const { theme } = useUnistyles();
  const { uuid } = useLocalSearchParams<{ uuid: string }>();
  const { value: deal } = useLoaded(() => loadDeal(String(uuid)));
  const day = (iso: string) => new Date(`${iso}T12:00:00`).toLocaleDateString(i18n.language, { weekday: 'short', month: 'short', day: 'numeric' });

  return (
    <Screen>
      <TitleBar title={deal ? deal.property : t('deals.title')} onBack={() => router.back()} />
      {deal === undefined ? (
        <ActivityIndicator color={theme.colors.action} />
      ) : deal === null ? (
        <Callout tone="warning" title={t('common.tryAgain')} live />
      ) : (
        <>
          <Text tone="muted">{[deal.city, t(`deals.sides.${deal.side as 'buy'}`), t(`deals.stages.${deal.stage as 'active'}`)].filter(Boolean).join(' · ')}</Text>
          <Text>
            {[deal.price !== null ? t('deals.price', { price: money(deal.price, i18n.language) }) : null, deal.commission !== null ? t('deals.commission', { amount: money(deal.commission, i18n.language) }) : null]
              .filter(Boolean)
              .join(' · ')}
          </Text>
          {deal.read_only ? (
            <Card>
              <View style={styles.row}>
                <Icon icon={Lock} />
                <Text style={styles.flex}>{t('deals.readOnly', { tool: TOOL_NAMES[deal.source] ?? deal.source })}</Text>
              </View>
              {deal.external_url ? (
                <Button variant="secondary" icon={ExternalLink} label={t('deals.openIn', { tool: TOOL_NAMES[deal.source] ?? deal.source })} onPress={() => void WebBrowser.openBrowserAsync(deal.external_url ?? '')} />
              ) : null}
            </Card>
          ) : null}
          <SectionTitle>{t('deals.dates')}</SectionTitle>
          {deal.dates.length === 0 ? <Text tone="muted">{t('deals.noNext')}</Text> : null}
          {deal.dates.map((date) => (
            <View key={date.id} style={styles.row}>
              <Icon icon={date.done ? CheckCircle2 : CalendarClock} color={date.done ? 'success' : 'action'} size={18} />
              <Text style={styles.flex}>{`${date.name} · ${day(date.due_on)}`}</Text>
            </View>
          ))}
          {deal.parties.length > 0 ? <SectionTitle>{t('deals.parties')}</SectionTitle> : null}
          {deal.parties.map((p, i) => (
            <View key={`${p.name}-${i}`}>
              <Text weight="semibold">{p.name}</Text>
              <Text tone="muted">{[p.role, p.email, p.phone].filter(Boolean).join(' · ')}</Text>
            </View>
          ))}
          {deal.files.length > 0 ? <SectionTitle>{t('deals.files')}</SectionTitle> : null}
          {deal.files.map((f, i) => (
            <View key={`${f.name}-${i}`} style={styles.row}>
              <Icon icon={f.status === 'complete' ? CheckCircle2 : f.status === 'missing' ? CircleAlert : Clock} color={f.status === 'complete' ? 'success' : f.status === 'missing' ? 'warning' : 'textMuted'} size={18} />
              <Text style={styles.flex}>{f.name}</Text>
              <Text tone="muted">{t(`deals.fileStatus.${f.status as 'complete'}`)}</Text>
            </View>
          ))}
          {deal.signatures.length > 0 ? <SectionTitle>{t('deals.signatures.title')}</SectionTitle> : null}
          {deal.signatures.map((envelope) => (
            <Card key={envelope.uuid}>
              <Text weight="semibold">{envelope.subject}</Text>
              <Text tone={envelope.status === 'completed' ? 'success' : envelope.status === 'declined' || envelope.status === 'voided' ? 'danger' : 'muted'}>{t(`deals.signatures.statuses.${envelope.status}`)}</Text>
              {envelope.signers.map((s) => (
                <View key={`${s.email}-${s.routing_order}`} style={styles.row}>
                  <Icon icon={SIGNER_ICONS[s.status]} color={s.status === 'signed' ? 'success' : s.status === 'declined' ? 'danger' : 'textMuted'} size={18} />
                  <Text style={styles.flex}>{`${s.name} · ${t(`deals.signatures.signerStatuses.${s.status}`)}`}</Text>
                </View>
              ))}
              {envelope.filed_in ? <Text tone="muted">{t('deals.signatures.filedIn', { tool: TOOL_NAMES[envelope.filed_in] ?? envelope.filed_in })}</Text> : null}
            </Card>
          ))}
          {deal.kept_files.length > 0 ? <SectionTitle>{t('deals.signatures.kept')}</SectionTitle> : null}
          {deal.kept_files.map((file) => (
            <Button
              key={file.uuid}
              variant="secondary"
              icon={FileText}
              label={file.name}
              accessibilityHint={file.scan === 'clean' ? t(`deals.signatures.kinds.${file.kind}`) : t(`deals.signatures.scan.${file.scan}`)}
              disabled={file.url === null}
              onPress={() => (file.url ? void WebBrowser.openBrowserAsync(file.url) : undefined)}
            />
          ))}
        </>
      )}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  row: { flexDirection: 'row', alignItems: 'center', gap: theme.space.xs },
  flex: { flex: 1 },
}));
