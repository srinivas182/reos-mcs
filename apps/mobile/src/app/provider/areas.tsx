import { router, useFocusEffect } from 'expo-router';
import { MapPin } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { addArea, areaParts, loadOnboarding, removeArea, type Onboarding, type ServiceArea } from '@/lib/providers';
import { Button, Callout, EmptyState, Icon, ListRow, PillGroup, Screen, Text, TextField, TitleBar, useUndoToast } from '@/ui';

type Kind = ServiceArea['kind'];

/**
 * Service areas on the phone (B15.2, provider screen 4): cities, ZIP codes
 * and a radius around a ZIP code, each with the active agents there and the
 * providers in the category who serve it.
 */
export default function ProviderAreasScreen() {
  const { t } = useTranslation();
  const undo = useUndoToast();
  const [o, setO] = useState<Onboarding | null>(null);
  const [kind, setKind] = useState<Kind>('city');
  const [value, setValue] = useState('');
  const [miles, setMiles] = useState('10');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useFocusEffect(
    useCallback(() => {
      void loadOnboarding().then(setO);
    }, []),
  );

  const label = (a: ServiceArea) => {
    const p = areaParts(a);

    return p.kind === 'radius' ? t('providers.onboarding.areas.radiusLabel', { miles: p.miles ?? 0, zip: p.value }) : p.value;
  };

  const run = (work: () => Promise<Onboarding>, after?: () => void) => {
    setBusy(true);
    setError(null);
    work()
      .then((next) => {
        setO(next);
        after?.();
      })
      .catch((e: Error) => setError(e.message))
      .finally(() => setBusy(false));
  };

  return (
    <Screen>
      <TitleBar title={t('providers.onboarding.areas.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('providers.onboarding.areas.hint')}</Text>
      {error ? <Callout tone="danger" title={error} /> : null}
      <PillGroup label={t('providers.onboarding.areas.kind')} value={kind} options={(['city', 'zip', 'radius'] as const).map((k) => ({ value: k, label: t(`providers.onboarding.areas.kinds.${k}`) }))} onChange={setKind} />
      <TextField
        label={kind === 'city' ? t('providers.onboarding.areas.city') : t('providers.onboarding.areas.zip')}
        value={value}
        maxLength={kind === 'city' ? 80 : 5}
        keyboardType={kind === 'city' ? 'default' : 'number-pad'}
        onChangeText={setValue}
      />
      {kind === 'radius' ? <TextField label={t('providers.onboarding.areas.radius')} value={miles} maxLength={2} keyboardType="number-pad" onChangeText={setMiles} /> : null}
      <Button
        variant="secondary"
        label={t('providers.onboarding.areas.add')}
        loading={busy}
        disabled={value.trim() === ''}
        onPress={() =>
          run(
            () => addArea(kind === 'city' ? { kind, city: value } : { kind, postal_code: value, radius_miles: kind === 'radius' ? Number(miles) : undefined }),
            () => setValue(''),
          )
        }
      />
      {o && o.areas.length === 0 ? <EmptyState icon={<Icon icon={MapPin} color="action" size={32} />} title={t('providers.onboarding.areas.emptyTitle')} description={t('providers.onboarding.areas.empty')} /> : null}
      {o?.areas.map((a) => (
        <ListRow
          key={a.id}
          icon={MapPin}
          title={label(a)}
          subtitle={`${t('providers.onboarding.areas.stats', { agents: a.agents, providers: a.providers })}\n${t('providers.onboarding.areas.remove', { area: label(a) })}`}
          onPress={() =>
            run(
              () => removeArea(a.id),
              () =>
                undo({
                  message: t('providers.onboarding.areas.removed', { area: label(a) }),
                  onUndo: () => run(() => addArea({ kind: a.kind, city: a.city ?? undefined, postal_code: a.postal_code ?? undefined, radius_miles: a.radius_miles ?? undefined })),
                }),
            )
          }
        />
      ))}
    </Screen>
  );
}
