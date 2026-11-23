import { router, useLocalSearchParams } from 'expo-router';
import { Circle, CircleCheck, ExternalLink } from 'lucide-react-native';
import * as WebBrowser from 'expo-web-browser';
import { useTranslation } from 'react-i18next';

import { setRenewed, useWallets } from '@/lib/ce';
import { Button, Callout, Icon, ListRow, Screen, Text, TitleBar, useUndoToast } from '@/ui';

const day = (iso: string, locale: string) => new Intl.DateTimeFormat(locale, { dateStyle: 'long' }).format(new Date(`${iso.slice(0, 10)}T12:00:00`));

/**
 * Renew your licence (A11.2; agent screen 106): CE complete, the checklist
 * from the state's rules (CE reported, sponsoring broker, renew with TREC,
 * confirmed) and the expiry date. Open TREC renewal goes to TREC's site;
 * REOS checks TREC's record afterwards and marks it done. Renewal fees are
 * paid to TREC, not REOS.
 */
export default function RenewScreen() {
  const { t, i18n } = useTranslation();
  const toast = useUndoToast();
  const { id } = useLocalSearchParams<{ id: string }>();
  const { wallets, setWallets } = useWallets();
  const wallet = wallets?.find((w) => String(w.licence.id) === id) ?? null;

  if (wallet === null) return <Screen>{null}</Screen>;

  const { renewal } = wallet;
  const mark = async (renewed: boolean) => {
    const next = await setRenewed(wallet.licence.id, renewed);
    if (next) setWallets((all) => (all ?? []).map((w) => (w.licence.id === next.licence.id ? next : w)));
    if (renewed) toast({ message: t('ce.wallet.renew.renewedDone'), onUndo: () => void mark(false) });
  };

  return (
    <Screen>
      <TitleBar title={t('ce.wallet.renew.title')} onBack={() => router.back()} />
      <Callout
        tone={renewal.ready ? 'success' : 'info'}
        title={renewal.ready ? t('ce.wallet.renew.ready') : t('ce.wallet.renew.notReady')}
        body={renewal.due_on ? t('ce.wallet.renew.expires', { date: day(renewal.due_on, i18n.language) }) : undefined}
      />
      {renewal.renewed_until ? <Text variant="title">{t('ce.wallet.renew.confirmed', { date: day(renewal.renewed_until, i18n.language) })}</Text> : null}
      {renewal.steps.map((step) => (
        <ListRow
          key={step.code}
          icon={step.done ? CircleCheck : Circle}
          tone={step.done ? 'success' : 'info'}
          title={`${step.title} (${step.done ? t('ce.wallet.renew.done') : t('ce.wallet.renew.todo')})`}
          subtitle={step.detail}
          trailing={step.url && !step.done ? <Icon icon={ExternalLink} color="action" size={20} /> : undefined}
          onPress={step.url && !step.done ? () => void WebBrowser.openBrowserAsync(step.url ?? '') : undefined}
        />
      ))}
      <Text tone="muted">{t('ce.wallet.renew.fees')}</Text>
      {renewal.confirmed_at === null && renewal.due_on !== null ? (
        renewal.renewed_at === null ? (
          <Button label={t('ce.wallet.renew.renewed')} onPress={() => void mark(true)} fullWidth />
        ) : (
          <Button variant="secondary" label={t('ce.wallet.renew.undoRenewed')} onPress={() => void mark(false)} fullWidth />
        )
      ) : null}
    </Screen>
  );
}
