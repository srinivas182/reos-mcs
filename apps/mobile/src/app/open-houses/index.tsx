import { router, useFocusEffect } from 'expo-router';
import { DoorOpen } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { fromNow, loadOpenHouses, type OpenHouse, startOpenHouse } from '@/lib/openHouses';
import { Button, Callout, Card, EmptyState, Icon, ListRow, Screen, SectionTitle, Text, TextField, TitleBar } from '@/ui';

/**
 * Open houses on the phone (B14.3a, ADR 0066): start sign-in at the door,
 * then show the QR code or hand the phone to visitors.
 */
export default function OpenHousesScreen() {
  const { t, i18n } = useTranslation();
  const [houses, setHouses] = useState<OpenHouse[] | null>(null);
  const [address, setAddress] = useState('');
  const [city, setCity] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const when = new Intl.DateTimeFormat(i18n.language, { weekday: 'short', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });

  useFocusEffect(
    useCallback(() => {
      void loadOpenHouses().then(setHouses);
    }, []),
  );

  const start = async () => {
    setBusy(true);
    setError(null);
    try {
      const { starts, ends } = fromNow(new Date());
      const house = await startOpenHouse(address.trim(), city.trim() === '' ? null : city.trim(), starts, ends);
      setAddress('');
      setCity('');
      router.push({ pathname: '/open-houses/[id]', params: { id: house.id } });
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setBusy(false);
    }
  };

  return (
    <Screen>
      <TitleBar title={t('leads.openHouse.pageTitle')} onBack={() => router.back()} />
      <Text tone="muted">{t('leads.openHouse.pagePurpose')}</Text>
      {error ? <Callout tone="danger" title={error} /> : null}

      <Card>
        <SectionTitle>{t('leads.openHouse.start')}</SectionTitle>
        <TextField label={t('leads.openHouse.address')} value={address} maxLength={160} autoComplete="street-address" onChangeText={setAddress} />
        <TextField label={t('leads.openHouse.city')} value={city} maxLength={80} onChangeText={setCity} hint={t('leads.openHouse.startNow')} />
        <Button icon={DoorOpen} label={t('leads.openHouse.start')} loading={busy} disabled={address.trim().length < 3} onPress={() => void start()} />
      </Card>

      {houses && houses.length === 0 ? <EmptyState icon={<Icon icon={DoorOpen} color="action" size={32} />} title={t('leads.openHouse.none')} description={t('leads.openHouse.noneHint')} /> : null}
      {(houses ?? []).map((h) => (
        <ListRow
          key={h.id}
          icon={DoorOpen}
          tone={h.open ? 'success' : 'info'}
          title={h.city ? `${h.address}, ${h.city}` : h.address}
          subtitle={`${when.format(new Date(h.starts_at))} · ${h.open ? t('leads.openHouse.open') : t('leads.openHouse.closed')} · ${t('leads.openHouse.visits', { count: h.visits })}`}
          onPress={() => router.push({ pathname: '/open-houses/[id]', params: { id: h.id } })}
        />
      ))}
    </Screen>
  );
}
