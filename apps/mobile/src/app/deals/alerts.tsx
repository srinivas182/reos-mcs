import { router } from 'expo-router';
import { CalendarClock, FileWarning } from 'lucide-react-native';
import { ActivityIndicator } from 'react-native';
import { useTranslation } from 'react-i18next';
import { useUnistyles } from 'react-native-unistyles';

import { loadAlerts, useLoaded } from '@/lib/deals';
import { Callout, ListRow, Screen, SectionTitle, Text, TitleBar } from '@/ui';

/**
 * Deal alerts (Agent App screen 10, B9.1): deadlines in the next two weeks
 * and documents still missing, read from the transaction tool and the deals
 * tracked in REOS. Pushes come at 72 and 24 hours.
 */
export default function DealAlertsScreen() {
  const { t, i18n } = useTranslation();
  const { theme } = useUnistyles();
  const { value: alerts } = useLoaded(loadAlerts);
  const open = (uuid: string) => router.push({ pathname: '/deals/[uuid]', params: { uuid } });
  const day = (iso: string) => new Date(`${iso}T12:00:00`).toLocaleDateString(i18n.language, { weekday: 'short', month: 'short', day: 'numeric' });

  return (
    <Screen>
      <TitleBar title={t('deals.alerts.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('deals.alerts.description')}</Text>
      {alerts === undefined ? (
        <ActivityIndicator color={theme.colors.action} />
      ) : alerts === null ? (
        <Callout tone="warning" title={t('common.tryAgain')} live />
      ) : (
        <>
          <SectionTitle>{t('deals.alerts.deadlines')}</SectionTitle>
          {alerts.deadlines.length === 0 ? <Text tone="muted">{t('deals.alerts.none')}</Text> : null}
          {alerts.deadlines.map(({ deal, date }) => (
            <ListRow
              key={`${deal.uuid}-${date.id}`}
              icon={CalendarClock}
              tone={date.days <= 1 ? 'danger' : date.days <= 3 ? 'warning' : 'info'}
              title={`${date.name} · ${t('deals.days', { count: date.days })}`}
              subtitle={`${deal.property}\n${day(date.due_on)}`}
              onPress={() => open(deal.uuid)}
            />
          ))}
          <SectionTitle>{t('deals.alerts.missing')}</SectionTitle>
          {alerts.missing.length === 0 ? <Text tone="muted">{t('deals.alerts.noMissing')}</Text> : null}
          {alerts.missing.map(({ deal, documents }) => (
            <ListRow key={deal.uuid} icon={FileWarning} tone="warning" title={deal.property} subtitle={documents.join(', ')} onPress={() => open(deal.uuid)} />
          ))}
        </>
      )}
    </Screen>
  );
}
