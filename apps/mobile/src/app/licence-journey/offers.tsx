import { router, useFocusEffect } from 'expo-router';
import { Building2 } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { dollars } from '@/lib/bookings';
import { answerOffer, type CandidateOffers, loadOffers } from '@/lib/licence';
import { Button, Callout, Card, Chip, EmptyState, Icon, Screen, Text, TitleBar } from '@/ui';

/**
 * Broker offers on the phone (candidate screen 17, A10.3b): ranked by fit,
 * rating and terms; Accept shares contact details with that brokerage;
 * Decline hides it.
 */
export default function OffersScreen() {
  const { t, i18n } = useTranslation();
  const [offers, setOffers] = useState<CandidateOffers | null>(null);
  const [problem, setProblem] = useState<string | null>(null);

  useFocusEffect(
    useCallback(() => {
      void loadOffers().then(setOffers);
    }, []),
  );

  const answer = async (id: string, accept: boolean) => {
    try {
      setOffers(await answerOffer(id, accept));
      setProblem(null);
    } catch (e) {
      setProblem(e instanceof Error ? e.message : String(e));
    }
  };

  return (
    <Screen>
      <TitleBar title={t('licence.offers.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('licence.offers.purposeShort')}</Text>
      {problem ? <Callout tone="danger" title={problem} live /> : null}
      {offers && !offers.opted_in ? <EmptyState icon={<Icon icon={Building2} color="action" size={32} />} title={t('licence.offers.off')} description={t('licence.offers.offBody')} /> : null}
      {offers && offers.opted_in && offers.offers.length === 0 ? <EmptyState icon={<Icon icon={Building2} color="action" size={32} />} title={t('licence.offers.empty')} description={t('licence.offers.emptyBody')} /> : null}
      {(offers?.offers ?? []).map((o) => (
        <Card key={o.id}>
          <Text variant="bodyLarge" weight="semibold">
            {o.brokerage}
          </Text>
          <Text weight="bold">{t('licence.offers.split', { percent: o.split_percent })}</Text>
          <View style={styles.chips}>
            <Chip tone="neutral" label={t('licence.offers.office', { city: o.office_city })} />
            {o.fit ? <Chip tone="success" label={t('licence.offers.fit')} /> : null}
            {o.mentoring ? <Chip tone="info" label={t('licence.offers.mentoring')} /> : null}
            {o.ai_help ? <Chip tone="info" label={t('licence.offers.aiHelp')} /> : null}
          </View>
          <Text>{`${o.monthly_fee_cents === 0 ? t('licence.offers.noFee') : t('licence.offers.fee', { fee: dollars(o.monthly_fee_cents, i18n.language) })}${o.fees ? ` · ${o.fees}` : ''}`}</Text>
          {o.training ? <Text>{`${t('licence.offers.training')}: ${o.training}`}</Text> : null}
          <Text variant="caption" tone="muted">
            {o.rating === null ? t('licence.offers.noRating') : t('licence.offers.rating', { rating: o.rating, count: o.ratings })}
          </Text>
          {o.message ? <Text tone="muted">{`“${o.message}”`}</Text> : null}
          {o.status === 'accepted' ? (
            <Text tone="success">{t('licence.offers.acceptedNote', { brokerage: o.brokerage })}</Text>
          ) : (
            <View style={styles.chips}>
              <Button label={t('licence.offers.accept')} onPress={() => void answer(o.id, true)} />
              <Button variant="secondary" label={t('licence.offers.decline')} onPress={() => void answer(o.id, false)} />
            </View>
          )}
        </Card>
      ))}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  chips: { flexDirection: 'row', gap: theme.space.sm, flexWrap: 'wrap', alignItems: 'center' },
}));
