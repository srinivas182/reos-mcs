import * as DocumentPicker from 'expo-document-picker';
import { router, useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { addLicence, loadOnboarding, saveBusiness, type Onboarding } from '@/lib/providers';
import { Button, Callout, Screen, Text, TextField, TitleBar } from '@/ui';

const KINDS = ['nmls', 'trec_inspector', 'tdi'] as const;
const STATUSES = ['verified', 'inactive', 'review', 'rejected'] as const;
const pick = <T extends string>(list: readonly T[], v: string | null): T | null => list.find((x) => x === v) ?? null;

/**
 * Your license on the phone (B15.2, provider screen 3): the licence the
 * category asks for, or business details and a photo ID when it has none.
 */
export default function ProviderLicenceScreen() {
  const { t } = useTranslation();
  const [o, setO] = useState<Onboarding | null>(null);
  const [number, setNumber] = useState('');
  const [name, setName] = useState('');
  const [address, setAddress] = useState('');
  const [photo, setPhoto] = useState<{ uri: string; name: string; type: string } | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useFocusEffect(
    useCallback(() => {
      void loadOnboarding().then((next) => {
        setO(next);
        setName(next?.licence.business?.name ?? '');
        setAddress(next?.licence.business?.address ?? '');
      });
    }, []),
  );

  const kind = pick(KINDS, o?.licence.kind ?? null);
  const current = o?.licence.licences.find((l) => l.kind === (kind ?? 'business_id'));
  const status = pick(STATUSES, current?.status ?? null);

  const run = (work: () => Promise<unknown>) => {
    setBusy(true);
    setError(null);
    work()
      .then(() => loadOnboarding().then(setO))
      .catch((e: Error) => setError(e.message))
      .finally(() => setBusy(false));
  };

  return (
    <Screen>
      <TitleBar title={kind ? t('providers.onboarding.licence.title') : t('providers.onboarding.business.title')} onBack={() => router.back()} />
      {error ? <Callout tone="danger" title={error} /> : null}
      {o && o.joining.category === null ? <Callout tone="info" title={t('providers.join.errors.chooseFirst')} /> : null}
      {current && status ? <Callout tone={status === 'verified' ? 'success' : status === 'review' ? 'info' : 'danger'} title={`${t(`providers.licences.kinds.${kind ?? 'business_id'}`)} · ${t(`providers.licences.statuses.${status}`)}`} /> : null}
      {o && o.joining.category && kind ? (
        <>
          <Text tone="muted">{t(`providers.onboarding.licence.${kind}`)}</Text>
          <TextField label={t('providers.onboarding.licence.number')} value={number} maxLength={40} onChangeText={setNumber} />
          <Button label={t('providers.onboarding.licence.check')} loading={busy} disabled={number.trim().length < 3} onPress={() => run(() => addLicence(kind, number))} />
        </>
      ) : null}
      {o && o.joining.category && !kind ? (
        <>
          <Text tone="muted">{t('providers.onboarding.business.hint')}</Text>
          <TextField label={t('providers.onboarding.business.name')} value={name} maxLength={160} onChangeText={setName} />
          <TextField label={t('providers.onboarding.business.address')} value={address} maxLength={240} onChangeText={setAddress} />
          <Text tone="muted">{current ? t('providers.onboarding.business.idOnFile') : t('providers.onboarding.business.idHint')}</Text>
          <Button
            variant="secondary"
            label={photo ? photo.name : t('providers.onboarding.business.id')}
            onPress={() =>
              void DocumentPicker.getDocumentAsync({ type: ['image/jpeg', 'image/png', 'application/pdf'], copyToCacheDirectory: true }).then((picked) => {
                const file = picked.canceled ? undefined : picked.assets[0];
                if (file) setPhoto({ uri: file.uri, name: file.name, type: file.mimeType ?? 'image/jpeg' });
              })
            }
          />
          <Button label={t('providers.onboarding.business.save')} loading={busy} disabled={name.trim().length < 2 || address.trim().length < 5} onPress={() => run(() => saveBusiness(name, address, photo))} />
        </>
      ) : null}
    </Screen>
  );
}
