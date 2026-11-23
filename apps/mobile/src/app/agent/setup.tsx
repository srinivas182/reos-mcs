import { router } from 'expo-router';
import { BadgeCheck } from 'lucide-react-native';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { FlagList } from '@/features/agent/FlagList';
import { GoalList } from '@/features/agent/GoalList';
import { setFlags, setGoal, useMode, type Flag, type Goal } from '@/lib/agent';
import { Button, Callout, Card, Icon, Screen, Text, TitleBar } from '@/ui';

/**
 * The agent's welcome, goal and flags on the phone (A11.1a; screens 4, 5
 * and 6): the licence confirmed with three promises, one question that sets
 * Today, and optional work-style flags.
 */
export default function AgentSetupScreen() {
  const { t } = useTranslation();
  const { mode } = useMode();
  const [step, setStep] = useState<'welcome' | 'goal' | 'flags'>('welcome');
  const [goal, setChosen] = useState<Goal | null>(null);
  const [flags, setChosenFlags] = useState<Flag[] | null>(null);
  const [problem, setProblem] = useState<string | null>(null);

  if (mode === null) return <Screen>{null}</Screen>;

  const chosen = goal ?? ((mode.goal ?? mode.suggested_default) as Goal);
  const run = async (work: () => Promise<unknown>, next: () => void) => {
    try {
      await work();
      setProblem(null);
      next();
    } catch (e) {
      setProblem(e instanceof Error ? e.message : String(e));
    }
  };

  return (
    <Screen>
      <TitleBar title={step === 'flags' ? t('agents.modes.flagsTitle') : t('agents.modes.setupPrompt.title')} onBack={() => router.back()} />
      {problem ? <Callout tone="danger" title={problem} live /> : null}
      {step === 'welcome' ? (
        <>
          <Text variant="headline">{t('agents.modes.welcome.title', { name: mode.welcome.name })}</Text>
          {mode.welcome.verified ? (
            <Card style={{ gap: 4 }}>
              <Text weight="semibold">
                <Icon icon={BadgeCheck} color="success" size={18} /> {t('agents.modes.welcome.verified')}
              </Text>
              {mode.welcome.licence && mode.welcome.state ? <Text>{t('agents.modes.welcome.licence', { state: mode.welcome.state, number: mode.welcome.licence })}</Text> : null}
              {mode.welcome.brokerage ? <Text tone="muted">{t('agents.modes.welcome.brokerage', { brokerage: mode.welcome.brokerage })}</Text> : null}
            </Card>
          ) : null}
          {(['tools', 'plan', 'ce'] as const).map((key, i) => (
            <Text key={key}>
              {i + 1}. {t(`agents.modes.welcome.steps.${key}`)}
            </Text>
          ))}
          <Button label={t('agents.modes.welcome.start')} onPress={() => setStep('goal')} />
        </>
      ) : null}
      {step === 'goal' ? (
        <>
          <Text tone="muted">{t('agents.modes.description')}</Text>
          <GoalList mode={mode} value={chosen} onChange={setChosen} />
          <Button label={t('agents.modes.continue')} onPress={() => void run(() => setGoal(chosen, mode.needs_setup ? 'setup' : 'manual'), () => setStep('flags'))} />
        </>
      ) : null}
      {step === 'flags' ? (
        <>
          <Text tone="muted">{t('agents.modes.flagsBody')}</Text>
          <FlagList value={flags ?? (mode.flags as Flag[])} onChange={setChosenFlags} />
          <Button label={t('agents.modes.saveFlags')} onPress={() => void run(() => setFlags(flags ?? (mode.flags as Flag[])), () => router.replace('/'))} />
          <Button variant="secondary" label={t('agents.modes.skip')} onPress={() => router.replace('/')} />
        </>
      ) : null}
    </Screen>
  );
}
