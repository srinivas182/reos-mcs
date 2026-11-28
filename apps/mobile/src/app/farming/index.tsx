import { router, useFocusEffect } from 'expo-router';
import { MapPin } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { addArea, type AreaForm, canAdd, type FarmArea, loadAreas } from '@/lib/farming';
import { Button, Callout, Card, EmptyState, Icon, ListRow, Screen, SectionTitle, Text, TextField, TitleBar } from '@/ui';

const blank: AreaForm = { name: '', place: '', tag: '', address: '' };

/**
 * Farming on the phone (B14.3c): the areas the agent farms, and adding one
 * (a city or ZIP code in their MLS, the contacts' tag, their mailing address).
 */
export default function FarmingScreen() {
  const { t, i18n } = useTranslation();
  const [areas, setAreas] = useState<FarmArea[] | null>(null);
  const [form, setForm] = useState<AreaForm>(blank);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const day = new Intl.DateTimeFormat(i18n.language, { month: 'short', day: 'numeric' });
  const set = (key: keyof AreaForm) => (value: string) => setForm((now) => ({ ...now, [key]: value }));

  useFocusEffect(
    useCallback(() => {
      void loadAreas().then(setAreas);
    }, []),
  );

  const add = async () => {
    setBusy(true);
    setError(null);
    try {
      const made = await addArea(form);
      setAreas((now) => [...(now ?? []), made]);
      setForm({ ...blank, address: form.address });
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setBusy(false);
    }
  };

  return (
    <Screen>
      <TitleBar title={t('leads.farm.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('leads.farm.purpose')}</Text>
      {error ? <Callout tone="danger" title={error} /> : null}

      {areas && areas.length === 0 ? <EmptyState icon={<Icon icon={MapPin} color="action" size={32} />} title={t('leads.farm.none')} description={t('leads.farm.noneHint')} /> : null}
      {(areas ?? []).map((a) => (
        <ListRow
          key={a.id}
          icon={MapPin}
          title={a.name}
          subtitle={`${a.tag} · ${t('leads.farm.recipients', { count: a.recipients })} · ${a.last_sent_at ? t('leads.farm.lastSent', { date: day.format(new Date(a.last_sent_at)) }) : t('leads.farm.neverSent')}`}
          onPress={() => router.push({ pathname: '/farming/[id]', params: { id: a.id } })}
        />
      ))}

      <Card>
        <SectionTitle>{t('leads.farm.add')}</SectionTitle>
        <TextField label={t('leads.farm.name')} value={form.name} maxLength={80} onChangeText={set('name')} hint={t('leads.farm.nameHint')} />
        <TextField label={`${t('leads.farm.city')} / ${t('leads.farm.postalCode')}`} value={form.place} maxLength={80} onChangeText={set('place')} hint={t('leads.farm.placeHint')} />
        <TextField label={t('leads.farm.tag')} value={form.tag} maxLength={60} autoCapitalize="none" onChangeText={set('tag')} hint={t('leads.farm.tagHint')} />
        <TextField label={t('leads.farm.address')} value={form.address} maxLength={200} autoComplete="street-address" onChangeText={set('address')} hint={t('leads.farm.addressHint')} />
        <Button icon={MapPin} label={t('leads.farm.add')} loading={busy} disabled={!canAdd(form)} onPress={() => void add()} />
      </Card>
    </Screen>
  );
}
