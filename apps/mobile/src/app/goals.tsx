import { router, useFocusEffect } from 'expo-router';
import { CheckCircle2, Circle, Flame, Gift } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Share } from 'react-native';

import { loadToday, type Today } from '@/lib/rewards';
import { Button, Card, ListRow, Screen, SectionTitle, Text, TitleBar } from '@/ui';

type GoalKey = 'conversations' | 'ce_lesson' | 'follow_ups' | 'post';

/**
 * Daily goals and points (agent screen 111; A13.3a): today's goals for the
 * agent's mode with their points, the streak and Share, and bonus
 * campaigns from the brokerage. Points come only from the agent's own work.
 */
export default function GoalsScreen() {
  const { t, i18n } = useTranslation();
  const [today, setToday] = useState<Today | null>(null);

  useFocusEffect(
    useCallback(() => {
      void loadToday().then(setToday);
    }, []),
  );

  const day = (d: string) => new Intl.DateTimeFormat(i18n.language, { month: 'long', day: 'numeric', timeZone: 'UTC' }).format(new Date(`${d}T12:00:00Z`));

  return (
    <Screen>
      <TitleBar title={t('rewards.today')} onBack={() => router.back()} />
      <Text tone="muted">{t('rewards.purpose')}</Text>
      {today ? (
        <>
          <Card>
            <Text weight="semibold">{today.streak > 0 ? t('rewards.streak', { count: today.streak }) : t('rewards.noStreak')}</Text>
            <Text tone="muted">{`${t('rewards.pointsToday', { count: today.points_today })}${today.bonus_today > 0 ? ` · ${t('rewards.bonusToday', { count: today.bonus_today })}` : ''}`}</Text>
            {today.best_streak > 0 ? <Text tone="muted">{t('rewards.bestStreak', { count: today.best_streak })}</Text> : null}
          </Card>
          {today.streak > 0 ? <Button variant="secondary" label={t('rewards.share')} onPress={() => void Share.share({ message: t('rewards.shareText', { count: today.streak }) })} /> : null}

          {today.goals.map((g) => (
            <ListRow
              key={g.key}
              icon={g.met ? CheckCircle2 : Circle}
              tone={g.met ? 'success' : 'info'}
              title={t(`rewards.goals.${g.key as GoalKey}`)}
              subtitle={[t('rewards.goalProgress', { done: g.done, target: g.target }), t('rewards.goalPoints', { count: g.points }), g.bonus > 0 ? t('rewards.goalBonus', { count: g.bonus }) : null].filter(Boolean).join(' · ')}
            />
          ))}
          <Text>{today.all_done ? t('rewards.allDone') : t('rewards.allDoneHint', { count: today.all_done_points })}</Text>

          {today.campaigns.length > 0 ? <SectionTitle>{t('rewards.campaigns')}</SectionTitle> : null}
          {today.campaigns.map((c) => (
            <ListRow
              key={c.id}
              icon={Flame}
              tone="warning"
              title={c.title}
              subtitle={t('rewards.campaignLine', { title: c.title, multiplier: c.multiplier, goal: c.goal === 'all' ? t('rewards.allGoals') : t(`rewards.goals.${c.goal as GoalKey}`), date: day(c.ends_on) })}
            />
          ))}

          <ListRow icon={Gift} tone="info" title={t('rewards.balance')} subtitle={t('rewards.optionPoints', { count: today.balance })} onPress={() => router.push('/rewards')} />
        </>
      ) : null}
    </Screen>
  );
}
