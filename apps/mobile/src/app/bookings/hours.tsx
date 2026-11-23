import { router } from 'expo-router';
import { useEffect, useState } from 'react';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { dollars, loadOffer, type OfferLimits, saveOffer, type SessionOffer } from '@/lib/bookings';
import { Button, Callout, Card, Screen, SwitchRow, Text, TextField, TitleBar, useUndoToast } from '@/ui';

const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as const;

const blank = (): SessionOffer => ({
  bio: null,
  languages: ['en'],
  topics: [],
  hours: { mon: [['13:00', '17:00']] },
  timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || 'America/Chicago',
  price_30_cents: 3500,
  price_60_cents: 6000,
  active: true,
});

/**
 * Hours and prices on the phone (instructor screen i14 › Hours, A10.2a):
 * taking bookings, prices, and one range of hours a day (the web sets
 * more than one).
 */
export default function HoursScreen() {
  const { t, i18n } = useTranslation();
  const toast = useUndoToast();
  const [offer, setOffer] = useState<SessionOffer | null>(null);
  const [limits, setLimits] = useState<OfferLimits>({ max_30_cents: null, max_60_cents: null });
  const [busy, setBusy] = useState(false);
  const [problem, setProblem] = useState<string | null>(null);

  useEffect(() => {
    void loadOffer().then((found) => {
      setOffer(found.offer ?? blank());
      setLimits(found.limits);
    });
  }, []);

  if (!offer) {
    return <Screen>{null}</Screen>;
  }

  const set = (patch: Partial<SessionOffer>) => setOffer({ ...offer, ...patch });
  const setDay = (day: string, range: [string, string] | null) => {
    const hours = { ...offer.hours };
    if (range === null) {
      delete hours[day];
    } else {
      hours[day] = [range];
    }
    set({ hours });
  };

  const save = async () => {
    setBusy(true);
    setProblem(null);
    try {
      setOffer(await saveOffer(offer));
      toast({ message: t('bookings.hours.saved') });
    } catch (e) {
      setProblem(e instanceof Error ? e.message : String(e));
    } finally {
      setBusy(false);
    }
  };

  return (
    <Screen
      footer={
        <View style={styles.footer}>
          <Button label={t('bookings.hours.save')} loading={busy} fullWidth onPress={() => void save()} />
        </View>
      }
    >
      <TitleBar title={t('bookings.hours.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('bookings.hours.purpose')}</Text>
      <SwitchRow label={t('bookings.hours.active')} value={offer.active} onValueChange={(active) => set({ active })} />
      <TextField
        label={t('bookings.hours.price', { count: 30 })}
        hint={limits.max_30_cents !== null ? t('bookings.hours.limitHint', { price: dollars(limits.max_30_cents, i18n.language) }) : undefined}
        keyboardType="number-pad"
        value={String(offer.price_30_cents / 100)} onChangeText={(v) => set({ price_30_cents: Math.round((Number(v) || 0) * 100) })} />
      <TextField
        label={t('bookings.hours.price', { count: 60 })}
        hint={limits.max_60_cents !== null ? t('bookings.hours.limitHint', { price: dollars(limits.max_60_cents, i18n.language) }) : undefined}
        keyboardType="number-pad"
        value={String(offer.price_60_cents / 100)} onChangeText={(v) => set({ price_60_cents: Math.round((Number(v) || 0) * 100) })} />
      <Text variant="caption" tone="muted">
        {t('bookings.hours.priceHint')}
      </Text>
      <Text weight="semibold">{t('bookings.hours.week', { zone: offer.timezone })}</Text>
      {DAYS.map((day) => {
        const range = offer.hours[day]?.[0] ?? null;
        const name = t(`bookings.days.${day}`);

        return (
          <Card key={day}>
            <SwitchRow label={name} value={range !== null} onValueChange={(on) => setDay(day, on ? ['13:00', '17:00'] : null)} />
            {range ? (
              <View style={styles.row}>
                <View style={styles.flex}>
                  <TextField label={t('bookings.hours.from', { day: name })} value={range[0]} maxLength={5} onChangeText={(v) => setDay(day, [v, range[1]])} />
                </View>
                <View style={styles.flex}>
                  <TextField label={t('bookings.hours.to', { day: name })} value={range[1]} maxLength={5} onChangeText={(v) => setDay(day, [range[0], v])} />
                </View>
              </View>
            ) : null}
          </Card>
        );
      })}
      {problem ? <Callout tone="danger" title={problem} live /> : null}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  footer: { paddingHorizontal: theme.m.gutter, paddingBottom: theme.space.xl },
  row: { flexDirection: 'row', gap: theme.space.sm },
  flex: { flex: 1 },
}));
