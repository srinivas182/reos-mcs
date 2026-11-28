import * as WebBrowser from 'expo-web-browser';
import { router } from 'expo-router';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { webBaseUrl } from '@/lib/api';
import { chooseSeats, loadSeats, type Payer, PAYERS, type SeatsPlan } from '@/lib/brokerage';
import { Button, Callout, Card, OptionGroup, Screen, SwitchRow, Text, TitleBar, useUndoToast } from '@/ui';

/**
 * Seats and plan on the phone (A15.1b, broker journey screen 4): who pays
 * and pooled AI credits. More seats are bought on reos.com; nothing is sold
 * in the app.
 */
export default function SeatsScreen() {
  const { t, i18n } = useTranslation();
  const toast = useUndoToast();
  const [plan, setPlan] = useState<SeatsPlan | null>(null);
  const [payer, setPayer] = useState<Payer>('organisation');
  const [pooled, setPooled] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const n = new Intl.NumberFormat(i18n.language);

  useEffect(() => {
    void loadSeats().then((p) => {
      setPlan(p);
      if (p) {
        setPayer(p.payer);
        setPooled(p.credits_pooled);
      }
    });
  }, []);

  return (
    <Screen>
      <TitleBar title={t('brokerage.seats.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('brokerage.seats.purpose')}</Text>
      {error ? <Callout tone="danger" title={error} /> : null}
      {plan ? (
        <>
          <Text>{plan.seats === null ? t('brokerage.seats.usageNoLimit', { used: plan.used, pending: plan.pending }) : t('brokerage.seats.usage', { used: plan.used, seats: plan.seats, pending: plan.pending })}</Text>
          <Card>
            <OptionGroup label={t('brokerage.seats.payer')} options={PAYERS.map((value) => ({ value, label: t(`brokerage.seats.payers.${value}`) }))} value={payer} onChange={setPayer} />
            <SwitchRow label={t('brokerage.seats.pooled')} description={t('brokerage.seats.pooledHint', { credits: n.format(plan.credits_per_seat) })} value={pooled} onValueChange={setPooled} />
            {plan.pool !== null ? <Text>{t('brokerage.seats.pool', { credits: n.format(plan.pool) })}</Text> : null}
            <Text tone="muted">{t('brokerage.seats.candidates', { credits: n.format(plan.candidate_pool) })}</Text>
          </Card>
          <Button
            label={t('brokerage.seats.save')}
            loading={busy}
            onPress={() => {
              setBusy(true);
              setError(null);
              chooseSeats(payer, pooled)
                .then((p) => {
                  setPlan(p);
                  toast({ message: t('brokerage.seats.saved') });
                })
                .catch((e: Error) => setError(e.message))
                .finally(() => setBusy(false));
            }}
          />
          <Button variant="secondary" label={t('brokerage.seats.more')} onPress={() => void WebBrowser.openBrowserAsync(`${webBaseUrl()}/settings/billing`)} />
        </>
      ) : null}
    </Screen>
  );
}
