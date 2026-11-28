import { Image } from 'expo-image';
import { router, useFocusEffect, useLocalSearchParams } from 'expo-router';
import { Send, Share2, Tablet, UserRound } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { Share } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { loadOpenHouses, loadVisits, type OpenHouse, type OpenHouseVisit, pending, sendPending } from '@/lib/openHouses';
import { Button, Callout, Card, EmptyState, Icon, ListRow, Screen, SectionTitle, Text, TitleBar } from '@/ui';

/**
 * One open house (B14.3a): the QR code to show by the door, the link to
 * share, sign-in on this phone, and who signed in, each a contact.
 */
export default function OpenHouseScreen() {
  const { t } = useTranslation();
  const { id } = useLocalSearchParams<{ id: string }>();
  const [house, setHouse] = useState<OpenHouse | null>(null);
  const [visits, setVisits] = useState<OpenHouseVisit[] | null>(null);
  const [waiting, setWaiting] = useState(0);
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    setWaiting(await sendPending(id));
    const [houses, list] = await Promise.all([loadOpenHouses(), loadVisits(id)]);
    setHouse(houses.find((h) => h.id === id) ?? null);
    setVisits(list);
  }, [id]);

  useFocusEffect(
    useCallback(() => {
      setWaiting(pending(id).length);
      void load();
    }, [id, load]),
  );

  if (!house)
    return (
      <Screen>
        <TitleBar title={t('leads.openHouse.pageTitle')} onBack={() => router.back()} />
      </Screen>
    );

  const place = house.city ? `${house.address}, ${house.city}` : house.address;

  return (
    <Screen>
      <TitleBar title={place} onBack={() => router.back()} />
      <Callout tone={house.open ? 'success' : 'info'} title={house.open ? t('leads.openHouse.open') : t('leads.openHouse.closed')} />

      {waiting > 0 ? (
        <Card>
          <Text weight="medium">{t('leads.openHouse.waiting', { count: waiting })}</Text>
          <Button
            variant="secondary"
            icon={Send}
            label={t('leads.openHouse.sendNow')}
            loading={busy}
            onPress={() => {
              setBusy(true);
              void load().finally(() => setBusy(false));
            }}
          />
        </Card>
      ) : null}

      <Card>
        <Image source={{ uri: house.qr_url }} style={styles.qr} contentFit="contain" accessibilityLabel={t('leads.openHouse.qrAlt', { address: house.address })} />
        <Text tone="muted">{house.url}</Text>
        <Button icon={Share2} label={t('listings.post.share')} onPress={() => void Share.share({ message: house.url })} />
        {house.open ? <Button variant="secondary" icon={Tablet} label={t('leads.openHouse.kiosk')} onPress={() => router.push({ pathname: '/open-houses/kiosk/[id]', params: { id: house.id } })} /> : null}
        {house.open ? <Text tone="muted">{t('leads.openHouse.kioskHint')}</Text> : null}
      </Card>

      <SectionTitle>{t('leads.openHouse.visitors')}</SectionTitle>
      {visits && visits.length === 0 ? <EmptyState icon={<Icon icon={UserRound} color="action" size={32} />} title={t('leads.openHouse.noVisits')} description={t('leads.openHouse.kioskHint')} /> : null}
      {(visits ?? []).map((v) => {
        const contact = v.contact;

        return (
          <ListRow
            key={v.id}
            icon={UserRound}
            title={v.name}
            subtitle={[v.email, v.phone, v.timeline ? t(`leads.timelines.${v.timeline}`) : null, v.pre_approved ? t('leads.openHouse.preApproved') : null, v.has_agent ? t('leads.openHouse.withAgent') : null].filter(Boolean).join(' · ')}
            onPress={contact === null ? undefined : () => router.push({ pathname: '/contacts/[id]', params: { id: contact } })}
          />
        );
      })}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  qr: { width: theme.m.buttonHeight * 5, height: theme.m.buttonHeight * 5, alignSelf: 'center', backgroundColor: theme.colors.surface },
}));
