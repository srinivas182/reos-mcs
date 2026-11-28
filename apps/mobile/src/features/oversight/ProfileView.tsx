import { BadgeCheck, Briefcase, Clock } from 'lucide-react-native';
import { useTranslation } from 'react-i18next';

import type { OversightProfile } from '@/lib/brokerage';
import { Callout, Card, ListRow, SectionTitle, Text } from '@/ui';

const STAGES = ['active', 'under_contract', 'closed', 'cancelled'] as const;
const SIDES = ['buy', 'sell', 'lease', 'both'] as const;

/**
 * An agent's summary as their broker sees it (A16.1): this month's activity,
 * plan, licences and CE, and deals by stage. The address shows only under
 * contract; never clients, contacts or notes. The agent sees the same view.
 */
export function ProfileView({ profile }: { profile: OversightProfile }) {
  const { t, i18n } = useTranslation();
  const date = (iso: string) => new Date(`${iso}T12:00:00`).toLocaleDateString(i18n.language);
  const ce = profile.agent.ce;

  return (
    <>
      <Callout tone="info" title={t('brokerage.oversight.privacy')} />
      <SectionTitle>{t('brokerage.oversight.profile.month')}</SectionTitle>
      <Card>
        <Text>{`${t('brokerage.oversight.profile.conversations')}: ${profile.activity.conversations}`}</Text>
        <Text>{`${t('brokerage.oversight.profile.appointments')}: ${profile.activity.appointments}`}</Text>
        <Text>{`${t('brokerage.oversight.profile.posts')}: ${profile.activity.posts}`}</Text>
        <Text>{`${t('brokerage.oversight.profile.ceHours')}: ${profile.activity.ce_hours}`}</Text>
      </Card>
      {profile.plan ? (
        <ListRow icon={Clock} title={t('brokerage.oversight.profile.plan')} subtitle={t('brokerage.oversight.profile.planWeek', { week: profile.plan.week, weeks: profile.plan.weeks, done: profile.plan.done, total: profile.plan.total })} />
      ) : null}
      <SectionTitle>{t('brokerage.oversight.profile.licences')}</SectionTitle>
      {profile.licences.map((l) => (
        <ListRow key={`${l.state}${l.number}`} icon={BadgeCheck} title={`${l.state} ${l.number}`} subtitle={[l.status, l.expires_on ? date(l.expires_on) : null].filter(Boolean).join(' · ')} />
      ))}
      {ce ? <Text>{`${ce.first_renewal ? `${t('brokerage.oversight.sae')} · ` : ''}${ce.complete ? t('brokerage.oversight.ceDone') : t('brokerage.oversight.ceDue', { hours: ce.hours_left, date: ce.due_on ? date(ce.due_on) : '—' })}`}</Text> : null}
      <SectionTitle>{t('brokerage.oversight.profile.deals')}</SectionTitle>
      {profile.deals.length === 0 ? (
        <Card>
          <Text>{t('brokerage.oversight.profile.noDeals')}</Text>
        </Card>
      ) : null}
      {profile.deals.map((d) => (
        <ListRow
          key={d.id}
          icon={Briefcase}
          title={t('brokerage.oversight.profile.dealRow', {
            stage: t(`brokerage.oversight.stages.${STAGES.find((s) => s === d.stage) ?? 'active'}`),
            side: t(`brokerage.oversight.sides.${SIDES.find((s) => s === d.side) ?? 'buy'}`),
            place: d.address ?? d.city ?? '—',
          })}
        />
      ))}
    </>
  );
}
