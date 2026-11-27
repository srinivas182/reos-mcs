import { router, useFocusEffect } from 'expo-router';
import { Coins, GraduationCap, History } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { loadRewards, reasonKey, type RewardOption, type RewardsSummary, spendPoints } from '@/lib/rewards';
import { Button, Callout, Card, EmptyState, Icon, ListRow, Screen, SectionTitle, Text, TitleBar } from '@/ui';

type GoalKey = 'conversations' | 'ce_lesson' | 'follow_ups' | 'post';

/**
 * Rewards (agent screen 112; A13.3a): the balance and its value (1,000
 * points = $10 inside REOS, no cash value), what points pay for — mentor
 * sessions, AI credits, a month of membership — and recent points.
 */
export default function RewardsScreen() {
  const { t, i18n } = useTranslation();
  const [rewards, setRewards] = useState<RewardsSummary | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [done, setDone] = useState(false);

  useFocusEffect(
    useCallback(() => {
      void loadRewards().then(setRewards);
    }, []),
  );

  const money = (cents: number) => new Intl.NumberFormat(i18n.language, { style: 'currency', currency: 'USD' }).format(cents / 100);
  const name = (o: RewardOption) => (o.kind === 'credit_pack' ? t('rewards.creditPack', { credits: o.credits ?? 0 }) : o.interval === 'year' ? t('rewards.membershipYear', { name: o.name }) : t('rewards.membership', { name: o.name }));
  const redeem = async (o: RewardOption) => {
    setBusy(true);
    setError(null);
    setDone(false);
    try {
      setRewards(await spendPoints(o.price));
      setDone(true);
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setBusy(false);
    }
  };

  return (
    <Screen>
      <TitleBar title={t('rewards.title')} onBack={() => router.back()} />
      {error ? <Callout tone="danger" title={error} /> : null}
      {done ? <Callout tone="success" title={t('rewards.redeemed')} live /> : null}
      {rewards ? (
        <>
          <Card>
            <Text weight="semibold">{t('rewards.balance')}</Text>
            <Text>{t('rewards.balanceValue', { points: rewards.balance.toLocaleString(i18n.language), value: money(rewards.value_cents) })}</Text>
            <Text tone="muted">{t('rewards.noCash')}</Text>
          </Card>

          <SectionTitle>{t('rewards.use')}</SectionTitle>
          <ListRow icon={GraduationCap} tone="info" title={t('rewards.mentorSessions')} subtitle={t('rewards.mentorSessionsHint')} onPress={() => router.push('/mentors')} />
          {rewards.options.length === 0 ? <EmptyState icon={<Icon icon={Coins} color="action" size={32} />} title={t('rewards.noOptions')} description={t('rewards.noOptionsHint')} /> : null}
          {rewards.options.map((o) => (
            <Card key={o.price}>
              <Text weight="semibold">{name(o)}</Text>
              <Text tone="muted">{t('rewards.optionPoints', { count: o.points })}</Text>
              <Button label={o.affordable ? t('rewards.redeem', { count: o.points }) : t('rewards.notEnough')} disabled={!o.affordable} loading={busy} onPress={() => void redeem(o)} />
            </Card>
          ))}

          <SectionTitle>{t('rewards.history')}</SectionTitle>
          {rewards.history.length === 0 ? <EmptyState icon={<Icon icon={History} color="action" size={32} />} title={t('rewards.noHistory')} description={t('rewards.noHistoryHint')} /> : null}
          {rewards.history.map((h, i) => {
            const r = reasonKey(h);

            return (
              <ListRow
                key={`${h.at}-${i}`}
                icon={Coins}
                tone={h.points > 0 ? 'success' : 'info'}
                title={t(r.key, { goal: r.goal ? t(`rewards.goals.${r.goal as GoalKey}`) : '' })}
                subtitle={`${h.points > 0 ? '+' : ''}${h.points} · ${new Intl.DateTimeFormat(i18n.language, { month: 'short', day: 'numeric' }).format(new Date(h.at))}`}
              />
            );
          })}
        </>
      ) : null}
    </Screen>
  );
}
