import { router, useFocusEffect } from 'expo-router';
import { FileSignature, Inbox } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { claimLead, loadMine, minutesLeft, signSplit, type TeamMine } from '@/lib/team';
import { Callout, EmptyState, Icon, ListRow, Screen, SectionTitle, Text, TitleBar } from '@/ui';

/**
 * Team leads on the phone (A17.3): a lead to claim opens from its push; the
 * first to claim in time gets it. Split sheets to sign.
 */
export default function TeamLeadsScreen() {
  const { t, i18n } = useTranslation();
  const [mine, setMine] = useState<TeamMine | null>(null);
  const [notice, setNotice] = useState<{ tone: 'success' | 'danger'; text: string } | null>(null);

  const load = useCallback(() => {
    void loadMine().then(setMine);
  }, []);

  useFocusEffect(load);

  const money = (c: number) => new Intl.NumberFormat(i18n.language, { style: 'currency', currency: 'USD', maximumFractionDigits: 0 }).format(c / 100);
  const run = (work: Promise<void>, text: string) =>
    work
      .then(() => setNotice({ tone: 'success', text }))
      .catch((e: Error) => setNotice({ tone: 'danger', text: e.message }))
      .finally(load);

  return (
    <Screen>
      <TitleBar title={t('teams.leads.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('teams.leads.purpose')}</Text>
      {notice ? <Callout tone={notice.tone} title={notice.text} /> : null}
      <SectionTitle>{t('teams.routing.offered')}</SectionTitle>
      {mine && mine.offered.length === 0 ? <EmptyState icon={<Icon icon={Inbox} color="action" size={32} />} title={t('teams.routing.offeredEmpty')} description={t('teams.routing.offeredEmptyHint')} /> : null}
      {mine?.offered.map((l) => (
        <ListRow
          key={l.id}
          icon={Inbox}
          tone="warning"
          title={t('teams.routing.claimFor', { name: l.name })}
          subtitle={t('teams.routing.offerBody', { minutes: minutesLeft(l.claim_by) })}
          onPress={() => run(claimLead(l.id), t('teams.routing.claimed', { name: l.name }))}
        />
      ))}
      <SectionTitle>{t('teams.splits.title')}</SectionTitle>
      {mine && mine.splits.length === 0 ? <Text tone="muted">{t('teams.splits.emptyHint')}</Text> : null}
      {mine?.splits.map((s) => (
        <ListRow
          key={s.id}
          icon={FileSignature}
          tone={s.status === 'signed' ? 'success' : 'info'}
          title={s.property}
          subtitle={`${t('teams.splits.shares', { brokerage: money(s.brokerage), team: money(s.team), agent: money(s.agent_share) })} · ${s.status === 'signed' ? t('teams.splits.statuses.signed') : t('teams.splits.sign')}`}
          onPress={s.status === 'signed' ? undefined : () => run(signSplit(s.id), t('teams.splits.signed'))}
        />
      ))}
    </Screen>
  );
}
