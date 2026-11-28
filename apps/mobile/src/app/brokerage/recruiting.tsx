import { router, useFocusEffect } from 'expo-router';
import { MessageSquare, Sparkles, Users } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { inviteAgent, loadOpenAgents, loadSponsored, type OpenAgent, type SponsoredBoard } from '@/lib/brokerage';
import { Callout, EmptyState, Icon, ListRow, Screen, SectionTitle, Text, TitleBar } from '@/ui';

const REASONS = ['closings', 'city', 'new'] as const;

/**
 * Recruiting on the phone (A17.2, broker screens 26 and 27): licensed
 * agents open to a move, with Invite to talk, and sponsored AI help.
 */
export default function RecruitingScreen() {
  const { t } = useTranslation();
  const [agents, setAgents] = useState<OpenAgent[]>([]);
  const [board, setBoard] = useState<SponsoredBoard | null>(null);
  const [error, setError] = useState<string | null>(null);

  useFocusEffect(
    useCallback(() => {
      void loadOpenAgents().then(setAgents);
      void loadSponsored().then(setBoard);
    }, []),
  );

  const reason = (r: string): string => {
    const found = REASONS.find((x) => x === r);

    return found ? t(`recruiting.agents.reasons.${found}`) : r;
  };
  const invite = (a: OpenAgent) => {
    inviteAgent(a.id)
      .then((id) => router.push({ pathname: '/messages/[id]', params: { id } }))
      .catch((e: Error) => setError(e.message));
  };

  return (
    <Screen>
      <TitleBar title={t('recruiting.title')} onBack={() => router.back()} />
      {error ? <Callout tone="danger" title={error} /> : null}
      <SectionTitle>{t('recruiting.agents.title')}</SectionTitle>
      <Text tone="muted">{t('recruiting.agents.purpose')}</Text>
      {agents.length === 0 ? <EmptyState icon={<Icon icon={Users} color="action" size={32} />} title={t('recruiting.agents.empty')} description={t('recruiting.agents.emptyHint')} /> : null}
      {agents.map((a) => (
        <ListRow
          key={a.id}
          icon={MessageSquare}
          title={a.name}
          subtitle={[t('recruiting.agents.closings', { count: a.closings }), ...a.reasons.map(reason)].join(' · ')}
          onPress={() => invite(a)}
        />
      ))}
      <SectionTitle>{t('recruiting.sponsored.title')}</SectionTitle>
      {board ? <Text>{t('recruiting.sponsored.pool', { credits: board.pool })}</Text> : null}
      {board?.candidates.map((s) => (
        <ListRow key={s.id} icon={Sparkles} tone={s.status === 'active' ? 'success' : 'info'} title={s.candidate} subtitle={t('recruiting.sponsored.usage', { used: s.used, cap: s.cap })} />
      ))}
    </Screen>
  );
}
