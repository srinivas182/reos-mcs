import { router } from 'expo-router';
import { FileCheck2, Lock } from 'lucide-react-native';
import { ActivityIndicator, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';

import { loadTransactions, money, TOOL_NAMES, useLoaded } from '@/lib/deals';
import { Callout, EmptyState, Icon, ListRow, Screen, Text, TitleBar } from '@/ui';

/**
 * Transactions: deal state (Connections and Sync screen 17, B9.1b): each
 * deal from the agent's transaction tools with its stage, documents
 * complete, next deadline and commission. Read only.
 */
export default function TransactionsScreen() {
  const { t, i18n } = useTranslation();
  const { theme } = useUnistyles();
  const { value: deals } = useLoaded(loadTransactions);

  return (
    <Screen>
      <TitleBar title={t('connections.transactions.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('connections.transactions.description')}</Text>
      {deals === undefined ? (
        <ActivityIndicator color={theme.colors.action} />
      ) : deals === null ? (
        <Callout tone="warning" title={t('common.tryAgain')} live />
      ) : deals.length === 0 ? (
        <EmptyState icon={<Icon icon={FileCheck2} color="action" size={32} />} title={t('connections.transactions.emptyTitle')} description={t('connections.transactions.empty')} />
      ) : (
        deals.map((deal) => (
          <ListRow
            key={deal.uuid}
            icon={FileCheck2}
            title={deal.property}
            subtitle={[
              `${t(`deals.stages.${deal.stage as 'active'}`)} · ${t('deals.documents', { complete: deal.documents.complete, total: deal.documents.total })}`,
              deal.next ? t('deals.next', { name: deal.next.name, date: t('deals.days', { count: deal.next.days }) }) : t('deals.noNext'),
              [deal.commission !== null ? t('deals.commission', { amount: money(deal.commission, i18n.language) }) : null, TOOL_NAMES[deal.source] ?? deal.source].filter(Boolean).join(' · '),
            ].join('\n')}
            onPress={() => router.push({ pathname: '/deals/[uuid]', params: { uuid: deal.uuid } })}
          />
        ))
      )}
      <View style={styles.row}>
        <Icon icon={Lock} size={18} color="textMuted" />
        <Text tone="muted" style={styles.flex}>
          {t('connections.transactions.readOnly')}
        </Text>
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  row: { flexDirection: 'row', alignItems: 'center', gap: theme.space.xs },
  flex: { flex: 1 },
}));
