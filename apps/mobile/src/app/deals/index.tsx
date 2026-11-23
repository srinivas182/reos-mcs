import { router } from 'expo-router';
import { BellRing, Handshake, House } from 'lucide-react-native';
import { ActivityIndicator, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';

import { DealRow } from '@/features/deals/DealRow';
import { loadPipeline, money, useLoaded } from '@/lib/deals';
import { Callout, Card, EmptyState, Icon, ListRow, Screen, SectionTitle, Text, TitleBar } from '@/ui';

/**
 * Deals pipeline (Agent App screen 9, B9.1): synced from the transaction
 * tool and the deals tracked in REOS: what's under contract, active clients
 * and pending commission. Deal alerts (screen 10) are one tap away.
 */
export default function PipelineScreen() {
  const { t, i18n } = useTranslation();
  const { theme } = useUnistyles();
  const { value: pipeline } = useLoaded(loadPipeline);

  return (
    <Screen>
      <TitleBar title={t('deals.pipeline.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('deals.pipeline.description')}</Text>
      {pipeline === undefined ? (
        <ActivityIndicator color={theme.colors.action} />
      ) : pipeline === null ? (
        <Callout tone="warning" title={t('common.tryAgain')} live />
      ) : (
        <>
          <View style={styles.tiles}>
            {(
              [
                [t('deals.pipeline.underContract'), String(pipeline.under_contract)],
                [t('deals.pipeline.active'), String(pipeline.active)],
                [t('deals.pipeline.pending'), money(pipeline.pending_commission, i18n.language)],
                [t('deals.pipeline.closedYear'), String(pipeline.closed_this_year)],
              ] as const
            ).map(([label, value]) => (
              <Card key={label} style={styles.tile}>
                <Text variant="caption" tone="muted">
                  {label}
                </Text>
                <Text variant="title">{value}</Text>
              </Card>
            ))}
          </View>
          <ListRow icon={BellRing} tone="warning" title={t('deals.pipeline.alerts')} subtitle={t('deals.alerts.description')} onPress={() => router.push('/deals/alerts')} />
          {/* My listings sit beside deals (B10.1). */}
          <ListRow icon={House} tone="info" title={t('listings.title')} subtitle={t('listings.description')} onPress={() => router.push('/deals/listings')} />
          {pipeline.deals.length === 0 ? (
            <EmptyState icon={<Icon icon={Handshake} color="action" size={32} />} title={t('deals.emptyTitle')} description={t('deals.empty')} />
          ) : (
            <>
              <SectionTitle>{t('deals.title')}</SectionTitle>
              {pipeline.deals.map((deal) => (
                <DealRow key={deal.uuid} deal={deal} />
              ))}
            </>
          )}
        </>
      )}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  tiles: { flexDirection: 'row', flexWrap: 'wrap', gap: theme.space.sm },
  tile: { flexBasis: '47%', flexGrow: 1 },
}));
