import { router } from 'expo-router';
import { Handshake } from 'lucide-react-native';
import { useTranslation } from 'react-i18next';

import { type DealCard, money, TOOL_NAMES } from '@/lib/deals';
import { ListRow } from '@/ui';

/** A deal in a list: property, side and price, and its next deadline. Opens the deal. */
export function DealRow({ deal }: { deal: DealCard }) {
  const { t, i18n } = useTranslation();
  const next = deal.next ? t('deals.next', { name: deal.next.name, date: t('deals.days', { count: deal.next.days }) }) : t('deals.noNext');
  const source = deal.read_only ? t('deals.from', { tool: TOOL_NAMES[deal.source] ?? deal.source }) : null;

  return (
    <ListRow
      icon={Handshake}
      tone={deal.next && deal.next.days <= 3 ? 'warning' : 'info'}
      title={deal.property}
      subtitle={[`${t(`deals.sides.${deal.side as 'buy'}`)}${deal.price !== null ? ` · ${money(deal.price, i18n.language)}` : ''}`, next, source].filter(Boolean).join('\n')}
      onPress={() => router.push({ pathname: '/deals/[uuid]', params: { uuid: deal.uuid } })}
    />
  );
}
