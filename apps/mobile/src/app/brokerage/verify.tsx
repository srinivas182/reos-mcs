import * as WebBrowser from 'expo-web-browser';
import { router } from 'expo-router';
import { BadgeCheck, Building2 } from 'lucide-react-native';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { webBaseUrl } from '@/lib/api';
import { type BrokerLookup, type BrokerRoleChoice, confirmBrokerage, firstOpen, lookupLicence, ROLES } from '@/lib/brokerage';
import { Button, Callout, Card, ListRow, OptionGroup, Screen, SectionTitle, Text, TextField, TitleBar } from '@/ui';

/**
 * Verify your brokerage on the phone (A15.1a, broker journey screen 2): the
 * TREC check, the brokerage and the role. A designated broker then chooses
 * the plan on reos.com (nothing is sold in the app).
 */
export default function VerifyBrokerageScreen() {
  const { t } = useTranslation();
  const [licence, setLicence] = useState('');
  const [found, setFound] = useState<BrokerLookup | null>(null);
  const [brokerage, setBrokerage] = useState<string | null>(null);
  const [role, setRole] = useState<BrokerRoleChoice>('designated_broker');
  const [done, setDone] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const run = async (work: () => Promise<void>) => {
    setBusy(true);
    setError(null);
    try {
      await work();
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setBusy(false);
    }
  };

  const chosen = found?.brokerages.find((b) => b.number === brokerage);

  return (
    <Screen>
      <TitleBar title={t('brokerage.verify.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('brokerage.verify.purpose')}</Text>
      {error ? <Callout tone="danger" title={error} /> : null}
      {done ? <Callout tone="info" title={done} /> : null}

      <Card>
        <TextField label={t('brokerage.verify.licence')} value={licence} keyboardType="number-pad" maxLength={20} onChangeText={setLicence} />
        <Button
          label={t('brokerage.verify.lookup')}
          loading={busy && found === null}
          disabled={licence.trim().length < 5}
          onPress={() =>
            void run(async () => {
              const next = await lookupLicence(licence);
              setFound(next);
              setBrokerage(firstOpen(next));
              setDone(null);
            })
          }
        />
      </Card>

      {found ? (
        <>
          <Callout
            tone={found.name_matches ? 'success' : 'warning'}
            title={`${t('brokerage.verify.found')}: ${found.licence.name}`}
            body={found.name_matches ? t('brokerage.verify.nameOk') : t('brokerage.verify.nameOther', { name: found.licence.name })}
          />
          <SectionTitle>{t('brokerage.verify.brokerages')}</SectionTitle>
          {found.brokerages.map((b) => (
            <ListRow
              key={b.number}
              icon={b.number === brokerage ? BadgeCheck : Building2}
              tone={b.number === brokerage ? 'success' : 'info'}
              title={`${b.name} · ${b.number}`}
              subtitle={`${b.kind === 'entity' ? t('brokerage.verify.entity') : t('brokerage.verify.individual')} · ${b.on_reos ? t('brokerage.verify.onReos') : t('brokerage.verify.sponsored', { count: b.sponsored })}`}
              onPress={b.on_reos ? undefined : () => setBrokerage(b.number)}
            />
          ))}
          <OptionGroup label={t('brokerage.verify.role')} options={ROLES.map((value) => ({ value, label: t(`brokerage.verify.roles.${value}`) }))} value={role} onChange={setRole} />
          <Button
            label={t('brokerage.verify.continue')}
            loading={busy}
            disabled={brokerage === null}
            onPress={() =>
              void run(async () => {
                const confirmed = await confirmBrokerage(licence, brokerage ?? '', role);

                if (confirmed.verification === null) {
                  setDone(t('brokerage.verify.askBroker', { brokerage: chosen?.name ?? '' }));

                  return;
                }

                setDone(t('brokerage.verify.verified', { name: confirmed.brokerage ?? '', count: confirmed.sponsored ?? 0 }));
                await WebBrowser.openBrowserAsync(`${webBaseUrl()}/start/brokerage`);
              })
            }
          />
        </>
      ) : null}
    </Screen>
  );
}
