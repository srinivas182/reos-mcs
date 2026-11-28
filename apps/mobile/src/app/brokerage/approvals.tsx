import { router, useFocusEffect } from 'expo-router';
import { ClipboardCheck, FileText, Globe, MessageCircleQuestion, TriangleAlert } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { itemsFor, loadApprovals, type ApprovalKind, type Approvals } from '@/lib/brokerage';
import { Callout, EmptyState, Icon, ListRow, PillGroup, Screen, Text, TitleBar } from '@/ui';

const ICONS = { post: FileText, page: Globe, question: MessageCircleQuestion } as const;

/**
 * Approvals on the phone (A15.3, broker journey screens 7 and 10): posts,
 * public pages and escalated questions, oldest first; tap one to review it.
 */
export default function ApprovalsScreen() {
  const { t } = useTranslation();
  const [queue, setQueue] = useState<Approvals | null>(null);
  const [filter, setFilter] = useState<ApprovalKind | 'all'>('all');

  useFocusEffect(
    useCallback(() => {
      void loadApprovals().then(setQueue);
    }, []),
  );

  const filters = queue
    ? [
        { value: 'all' as const, label: `${t('brokerage.approvals.filters.all')} (${queue.items.length})` },
        ...(queue.can.posts
          ? [
              { value: 'post' as const, label: `${t('brokerage.approvals.filters.post')} (${queue.counts.posts})` },
              { value: 'page' as const, label: `${t('brokerage.approvals.filters.page')} (${queue.counts.pages})` },
            ]
          : []),
        ...(queue.can.questions ? [{ value: 'question' as const, label: `${t('brokerage.approvals.filters.question')} (${queue.counts.questions})` }] : []),
      ]
    : [];
  const shown = queue ? itemsFor(queue, filter) : [];

  return (
    <Screen>
      <TitleBar title={t('brokerage.approvals.title')} onBack={() => router.back()} />
      <Text tone="muted">{queue?.scope === 'team' ? t('brokerage.approvals.teamPurpose', { team: queue.team ?? '' }) : t('brokerage.approvals.purpose')}</Text>
      {queue ? (
        <>
          {queue.counts.overdue > 0 ? <Callout tone="warning" title={t('brokerage.approvals.overdueCount', { count: queue.counts.overdue, hours: queue.fallback_hours })} /> : null}
          <PillGroup label={t('brokerage.approvals.filterLabel')} value={filter} options={filters} onChange={setFilter} />
          {shown.length === 0 ? <EmptyState icon={<Icon icon={ClipboardCheck} color="action" size={32} />} title={t('brokerage.approvals.emptyTitle')} description={t('brokerage.approvals.empty')} /> : null}
          {shown.map((item) => (
            <ListRow
              key={`${item.kind}:${item.id}`}
              icon={item.overdue ? TriangleAlert : ICONS[item.kind]}
              tone={item.overdue ? 'warning' : 'info'}
              title={`${t(`brokerage.approvals.kinds.${item.kind}`)} · ${item.agent}`}
              subtitle={`${item.summary}\n${item.hours < 0.1 ? t('brokerage.approvals.waitedNew') : t('brokerage.approvals.waited', { hours: Math.round(item.hours * 10) / 10 })}`}
              onPress={() => router.push({ pathname: '/brokerage/review', params: { kind: item.kind, id: item.id } })}
            />
          ))}
        </>
      ) : null}
    </Screen>
  );
}
