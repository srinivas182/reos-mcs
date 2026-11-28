import { router, useFocusEffect } from 'expo-router';
import { CircleCheck, TriangleAlert, UserRound } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { loadOversight, needingHelp, type OversightBoard, type OversightSignal } from '@/lib/brokerage';
import { Callout, EmptyState, Icon, ListRow, Screen, SectionTitle, Text, TitleBar } from '@/ui';

const STAGES = ['active', 'under_contract', 'closed', 'cancelled'] as const;

/**
 * Agents and deals on the phone (A16.1): who may need help and why, and
 * deals at risk. Counts and stages only; clients stay with the agent.
 */
export default function OversightScreen() {
  const { t } = useTranslation();
  const [board, setBoard] = useState<OversightBoard | null>(null);

  useFocusEffect(
    useCallback(() => {
      void loadOversight().then(setBoard);
    }, []),
  );

  const signal = (s: OversightSignal): string => {
    if (s === 'quiet') return t('brokerage.oversight.signals.quiet', { days: board?.settings.quiet_days ?? 14 });
    if (s === 'no_deal') return t('brokerage.oversight.signals.no_deal', { days: board?.settings.no_deal_days ?? 90 });
    if (s === 'ce_behind') return t('brokerage.oversight.signals.ce_behind');
    if (s === 'plan_behind') return t('brokerage.oversight.signals.plan_behind');
    if (s === 'deadline_missed') return t('brokerage.oversight.signals.deadline_missed');

    return t('brokerage.oversight.signals.new');
  };
  const help = board ? needingHelp(board) : [];

  return (
    <Screen>
      <TitleBar title={t('brokerage.oversight.title')} onBack={() => router.back()} />
      <Text tone="muted">{board?.scope === 'team' ? t('brokerage.oversight.teamPurpose', { team: board.team ?? '' }) : t('brokerage.oversight.purpose')}</Text>
      {board ? (
        <>
          <Callout tone="info" title={t('brokerage.oversight.counts', { needHelp: board.counts.need_help, agents: board.counts.agents, deals: board.counts.deals_at_risk })} body={t('brokerage.oversight.privacy')} />
          <SectionTitle>{t('brokerage.oversight.needHelp')}</SectionTitle>
          {help.length === 0 ? <EmptyState icon={<Icon icon={CircleCheck} color="action" size={32} />} title={t('brokerage.oversight.allGood')} description={t('brokerage.oversight.allGoodHint')} /> : null}
          {help.map((a) => (
            <ListRow
              key={a.id}
              icon={UserRound}
              tone="warning"
              title={a.team ? `${a.name} · ${a.team}` : a.name}
              subtitle={a.signals.map(signal).join(' · ')}
              onPress={() => router.push({ pathname: '/brokerage/agent/[id]', params: { id: String(a.id) } })}
            />
          ))}
          <SectionTitle>{t('brokerage.oversight.risk')}</SectionTitle>
          {board.at_risk.length === 0 ? <EmptyState icon={<Icon icon={CircleCheck} color="action" size={32} />} title={t('brokerage.oversight.noRisk')} description={t('brokerage.oversight.noRiskHint')} /> : null}
          {board.at_risk.map((d) => (
            <ListRow
              key={d.id}
              icon={TriangleAlert}
              tone="warning"
              title={`${d.agent} · ${t(`brokerage.oversight.stages.${STAGES.find((s) => s === d.stage) ?? 'active'}`)}`}
              subtitle={[
                d.address ?? d.city,
                d.reasons.map((r) => (r === 'date_soon' ? t('brokerage.oversight.reasons.date_soon') : r === 'date_missed' ? t('brokerage.oversight.reasons.date_missed') : t('brokerage.oversight.reasons.stuck'))).join(' · '),
              ]
                .filter(Boolean)
                .join('\n')}
              onPress={() => router.push({ pathname: '/brokerage/agent/[id]', params: { id: String(d.agent_id) } })}
            />
          ))}
        </>
      ) : null}
    </Screen>
  );
}
