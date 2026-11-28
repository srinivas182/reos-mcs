import { router, useFocusEffect } from 'expo-router';
import { ChartColumn, CircleCheck, ClipboardCheck, GraduationCap, TriangleAlert, UserRound } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { briefClear, loadBrief, type BrokerBrief } from '@/lib/brokerage';
import { EmptyState, Icon, ListRow, Screen, SectionTitle, Text, TitleBar } from '@/ui';

const STAGES = ['active', 'under_contract', 'closed', 'cancelled'] as const;
const REASONS = ['date_soon', 'date_missed', 'stuck'] as const;

/**
 * The broker's morning brief on the phone (A16.3): opened from the
 * weekday-morning notification. Counts and stages only.
 */
export default function BrokerBriefScreen() {
  const { t, i18n } = useTranslation();
  const [brief, setBrief] = useState<BrokerBrief | null>(null);

  useFocusEffect(
    useCallback(() => {
      void loadBrief().then(setBrief);
    }, []),
  );

  const money = (n: number) => new Intl.NumberFormat(i18n.language, { style: 'currency', currency: 'USD', maximumFractionDigits: 0 }).format(n);
  const signal = (s: string): string => {
    if (s === 'quiet') return t('brokerage.oversight.signals.quiet', { days: brief?.settings.quiet_days ?? 14 });
    if (s === 'no_deal') return t('brokerage.oversight.signals.no_deal', { days: brief?.settings.no_deal_days ?? 90 });
    if (s === 'ce_behind') return t('brokerage.oversight.signals.ce_behind');
    if (s === 'plan_behind') return t('brokerage.oversight.signals.plan_behind');
    if (s === 'deadline_missed') return t('brokerage.oversight.signals.deadline_missed');

    return t('brokerage.oversight.signals.new');
  };
  const stage = (s: string): string => {
    const found = STAGES.find((x) => x === s);

    return found ? t(`brokerage.oversight.stages.${found}`) : s;
  };
  const reason = (r: string): string => {
    const found = REASONS.find((x) => x === r);

    return found ? t(`brokerage.oversight.reasons.${found}`) : r;
  };

  return (
    <Screen>
      <TitleBar title={t('brokerage.brief.title')} onBack={() => router.back()} />
      {brief ? (
        <>
          <Text tone="muted">{t('brokerage.brief.purpose', { brokerage: brief.team ?? brief.brokerage })}</Text>
          {briefClear(brief) ? <EmptyState icon={<Icon icon={CircleCheck} color="action" size={32} />} title={t('brokerage.brief.allClear')} description={t('brokerage.oversight.allGoodHint')} /> : null}
          {brief.need_help.count > 0 ? (
            <>
              <SectionTitle>{t('brokerage.brief.needHelp', { count: brief.need_help.count })}</SectionTitle>
              {brief.need_help.agents.map((a) => (
                <ListRow key={a.id} icon={UserRound} tone="warning" title={a.name} subtitle={a.signals.map(signal).join(' · ')} onPress={() => router.push(`/brokerage/agent/${a.id}`)} />
              ))}
            </>
          ) : null}
          {brief.at_risk.count > 0 ? (
            <>
              <SectionTitle>{t('brokerage.brief.atRisk', { count: brief.at_risk.count })}</SectionTitle>
              {brief.at_risk.deals.map((d) => (
                <ListRow key={d.id} icon={TriangleAlert} tone="warning" title={d.agent} subtitle={[stage(d.stage), d.city, d.reasons.map(reason).join(', ')].filter(Boolean).join(' · ')} onPress={() => router.push('/brokerage/oversight')} />
              ))}
            </>
          ) : null}
          {brief.approvals.waiting > 0 ? (
            <ListRow
              icon={ClipboardCheck}
              tone={brief.approvals.overdue > 0 ? 'danger' : 'info'}
              title={t('brokerage.brief.approvals', { count: brief.approvals.waiting, overdue: brief.approvals.overdue })}
              onPress={() => router.push('/brokerage/approvals')}
            />
          ) : null}
          {brief.ce_due ? <ListRow icon={GraduationCap} tone="warning" title={t('brokerage.brief.ceDue', { count: brief.ce_due })} onPress={() => router.push('/brokerage/ce')} /> : null}
          <ListRow icon={ChartColumn} title={t('brokerage.brief.closings', { count: brief.closings.count, volume: money(brief.closings.volume) })} subtitle={t('brokerage.brief.openReports')} onPress={() => router.push('/brokerage/reports')} />
        </>
      ) : (
        <Text tone="muted">{t('announcements.receipts.loading')}</Text>
      )}
    </Screen>
  );
}
