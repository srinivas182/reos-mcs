import { router } from 'expo-router';
import { History } from 'lucide-react-native';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { FlagList } from '@/features/agent/FlagList';
import { GoalList } from '@/features/agent/GoalList';
import { setFlags, setGoal, useMode, type Flag, type Goal } from '@/lib/agent';
import { Callout, ListRow, Screen, SectionTitle, Text, TitleBar, useUndoToast } from '@/ui';

/**
 * My goal on the phone (screen 146, A11.1a): change the goal by hand, see
 * its history and locks, and set flags. Every feature stays reachable in
 * every mode.
 */
export default function MyGoalScreen() {
  const { t, i18n } = useTranslation();
  const toast = useUndoToast();
  const { mode, setMode } = useMode();
  const [problem, setProblem] = useState<string | null>(null);

  if (mode === null) return <Screen>{null}</Screen>;

  const save = async (work: () => ReturnType<typeof setGoal>) => {
    try {
      setMode(await work());
      setProblem(null);
      toast({ message: t('agents.modes.saved') });
    } catch (e) {
      setProblem(e instanceof Error ? e.message : String(e));
    }
  };
  const name = (goal: string) => t(`agents.modes.goals.${goal as Goal}.name`);
  const date = (iso: string) => new Intl.DateTimeFormat(i18n.language, { dateStyle: 'medium' }).format(new Date(iso));

  return (
    <Screen>
      <TitleBar title={t('agents.modes.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('agents.modes.description')}</Text>
      {problem ? <Callout tone="danger" title={problem} live /> : null}
      <GoalList mode={mode} value={mode.goal as Goal | null} onChange={(goal) => void save(() => setGoal(goal, 'manual'))} />
      <SectionTitle>{t('agents.modes.flagsTitle')}</SectionTitle>
      <FlagList value={mode.flags as Flag[]} onChange={(flags) => void save(() => setFlags(flags))} />
      {mode.history.length > 0 ? <SectionTitle>{t('agents.modes.history')}</SectionTitle> : null}
      {mode.history.map((h) => (
        <ListRow
          key={h.at}
          icon={History}
          tone="neutral"
          title={h.from ? t('agents.modes.historyEntry', { from: name(h.from), to: name(h.to) }) : t('agents.modes.historyFirst', { to: name(h.to) })}
          subtitle={`${t(`agents.modes.reasons.${h.reason as 'setup' | 'manual' | 'suggested'}`)} · ${date(h.at)}`}
        />
      ))}
    </Screen>
  );
}
