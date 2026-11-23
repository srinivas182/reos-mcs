import { router, useLocalSearchParams } from 'expo-router';
import { SearchX } from 'lucide-react-native';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ActivityIndicator, View } from 'react-native';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';

import { dollarsOf, loadCma, RADII, RANGES, type Cma, type Radius, type Range } from '@/lib/listings';
import { Callout, Card, EmptyState, Icon, PillGroup, Screen, SectionTitle, Text, TitleBar } from '@/ui';

/**
 * A CMA on the phone (B10.2b): the suggested range from nearby sales, the
 * median price per square foot, and the comparables nearest first, each
 * adjusted to this home's size, with its MLS attribution.
 */
export default function CmaScreen() {
  const { t, i18n } = useTranslation();
  const { theme } = useUnistyles();
  const { uuid } = useLocalSearchParams<{ uuid: string }>();
  const [radius, setRadius] = useState<Radius>('1');
  const [months, setMonths] = useState<Range>('6');
  const [cma, setCma] = useState<Cma | null | undefined>(undefined);
  const [problem, setProblem] = useState<string | null>(null);
  const dollars = (value: number | null) => dollarsOf(value, i18n.language);

  useEffect(() => {
    void loadCma(uuid, radius, months)
      .then((found) => {
        setCma(found);
        setProblem(null);
      })
      .catch((e: unknown) => {
        setCma(null);
        setProblem(e instanceof Error ? e.message : String(e));
      });
  }, [uuid, radius, months]);

  return (
    <Screen>
      <TitleBar title={t('listings.cma.title')} onBack={() => router.back()} />
      {cma ? <Text tone="muted">{t('listings.cma.description', { address: cma.subject.address })}</Text> : null}
      <PillGroup
        label={t('listings.cma.radius')}
        value={radius}
        options={RADII.map((r) => ({ value: r, label: t('listings.cma.radiusOption', { miles: r }) }))}
        onChange={(r) => {
          setCma(undefined);
          setRadius(r);
        }}
      />
      <PillGroup
        label={t('listings.cma.months')}
        value={months}
        options={RANGES.map((m) => ({ value: m, label: t('listings.cma.monthsOption', { count: Number(m) }) }))}
        onChange={(m) => {
          setCma(undefined);
          setMonths(m);
        }}
      />
      {problem ? <Callout tone="danger" title={problem} live /> : null}
      {cma === undefined ? (
        <ActivityIndicator color={theme.colors.action} />
      ) : cma === null ? null : (
        <>
          {cma.sample ? <Callout tone="info" title={t('listings.sample')} body={t('listings.sampleNote')} /> : null}
          {cma.comparables.length === 0 ? (
            <EmptyState icon={<Icon icon={SearchX} color="action" size={32} />} title={t('listings.cma.empty.title')} description={t('listings.cma.empty.description')} />
          ) : (
            <>
              <Card style={styles.gap}>
                <Text tone="muted">{t('listings.cma.suggested')}</Text>
                <Text variant="title">{cma.low === null || cma.high === null ? '—' : `${dollars(cma.low)} – ${dollars(cma.high)}`}</Text>
                <Text tone="muted">
                  {cma.sold >= 3 ? t('listings.cma.suggestedBody', { sold: cma.sold }) : t('listings.cma.suggestedAll', { count: cma.comparables.length })}
                </Text>
              </Card>
              <Card style={styles.gap}>
                <Text tone="muted">{t('listings.cma.median')}</Text>
                <Text variant="title">{dollars(cma.median_per_sqft)}</Text>
                <Text tone="muted">{t('listings.cma.soldActive', { sold: cma.sold, active: cma.active })}</Text>
              </Card>
              <SectionTitle>{t('listings.cma.comparables')}</SectionTitle>
              {cma.comparables.map(({ listing, distance, adjusted }) => (
                <Card key={listing.uuid} style={styles.gap}>
                  <Text weight="semibold">{listing.address}</Text>
                  <View style={styles.row}>
                    <Text>{t(`listings.status.${listing.status}`, { defaultValue: listing.status })}</Text>
                    <Text>{dollars(listing.status === 'closed' ? listing.close_price : listing.price)}</Text>
                    <Text tone="muted">{t('listings.cma.distance', { miles: distance.toFixed(1) })}</Text>
                  </View>
                  <Text>
                    {t('listings.cma.columns.adjusted')}: {dollars(adjusted)}
                  </Text>
                  <Text variant="caption" tone="muted">
                    {listing.attribution}
                  </Text>
                </Card>
              ))}
            </>
          )}
        </>
      )}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  gap: { gap: theme.space.xs },
  row: { flexDirection: 'row', flexWrap: 'wrap', gap: theme.space.md },
}));
