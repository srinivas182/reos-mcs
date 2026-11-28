import { router, useFocusEffect } from 'expo-router';
import { ChartColumn, RefreshCw, TriangleAlert, UserRound } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { loadClosings, PERIODS, type ClosingsReport, type ReportPeriod } from '@/lib/brokerage';
import { Callout, ChoiceRow, EmptyState, Icon, ListRow, Screen, SectionTitle, Text, TitleBar } from '@/ui';

/**
 * Brokerage reports on the phone (A16.3): closings and volume for a period,
 * matched to the transaction tool, with what's only in REOS by name.
 */
export default function ReportsScreen() {
  const { t, i18n } = useTranslation();
  const [period, setPeriod] = useState<ReportPeriod>('this_month');
  const [report, setReport] = useState<ClosingsReport | null>(null);

  useFocusEffect(
    useCallback(() => {
      void loadClosings(period).then(setReport);
    }, [period]),
  );

  const money = (n: number) => new Intl.NumberFormat(i18n.language, { style: 'currency', currency: 'USD', maximumFractionDigits: 0 }).format(n);
  const date = (iso: string) => new Date(iso).toLocaleDateString(i18n.language, { dateStyle: 'medium' });

  return (
    <Screen>
      <TitleBar title={t('brokerage.reports.title')} onBack={() => router.back()} />
      <Text tone="muted">{report?.scope === 'team' ? t('brokerage.reports.teamPurpose', { team: report.team ?? '' }) : t('brokerage.reports.purpose')}</Text>
      <SectionTitle>{t('brokerage.reports.period')}</SectionTitle>
      {PERIODS.map((p) => (
        <ChoiceRow key={p} title={t(`brokerage.reports.periods.${p}`)} selected={p === period} onPress={() => setPeriod(p)} />
      ))}
      {report ? (
        <>
          <Text variant="title">{t('brokerage.reports.totals', { count: report.totals.count, volume: money(report.totals.volume) })}</Text>
          <SectionTitle>{t('brokerage.reports.match.title')}</SectionTitle>
          {report.tools.length === 0 ? <Callout tone="info" title={t('brokerage.reports.match.noTool')} body={t('brokerage.reports.match.noToolHint')} /> : null}
          {report.tools.map((tool) => (
            <ListRow
              key={tool.connection}
              icon={RefreshCw}
              tone={tool.stale ? 'warning' : 'info'}
              title={t('brokerage.reports.match.tool', { tool: tool.name, count: tool.count, volume: money(tool.volume) })}
              subtitle={tool.stale ? t('brokerage.reports.match.stale') : tool.last_synced_at ? t('brokerage.reports.match.synced', { date: date(tool.last_synced_at) }) : t('brokerage.reports.match.neverSynced')}
            />
          ))}
          {report.reos_only.count === 0 && report.tools.length > 0 ? <Callout tone="success" title={t('brokerage.reports.match.matched')} /> : null}
          {report.reos_only.count > 0 ? (
            <>
              <Callout tone="warning" title={t('brokerage.reports.match.differences', { count: report.reos_only.count })} body={t('brokerage.reports.match.differencesHint')} />
              {report.reos_only.deals.map((d) => (
                <ListRow key={d.id} icon={TriangleAlert} tone="warning" title={d.property} subtitle={`${d.agent} · ${money(d.price ?? 0)} · ${date(d.closed_at)}`} />
              ))}
            </>
          ) : null}
          <SectionTitle>{t('brokerage.reports.byAgent')}</SectionTitle>
          {report.by_agent.length === 0 ? <EmptyState icon={<Icon icon={ChartColumn} color="action" size={32} />} title={t('brokerage.reports.empty')} description={t('brokerage.reports.emptyHint')} /> : null}
          {report.by_agent.map((a) => (
            <ListRow key={a.id} icon={UserRound} title={a.team ? `${a.name} · ${a.team}` : a.name} subtitle={t('brokerage.reports.totals', { count: a.count, volume: money(a.volume) })} />
          ))}
        </>
      ) : null}
    </Screen>
  );
}
