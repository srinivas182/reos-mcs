import { router } from 'expo-router';
import { Compass, Sparkles } from 'lucide-react-native';
import { useTranslation } from 'react-i18next';

import { snooze, setGoal, type Goal, type ModeState } from '@/lib/agent';
import { Button, Card, Chip, ListRow, Text } from '@/ui';

/**
 * The agent's mode on Today (A11.1a): "Pick your goal" until set, the mode
 * badge that opens My goal, and a suggested switch (screens 126, 132).
 */
export function ModeCard({ mode, onChange }: { mode: ModeState; onChange: (mode: ModeState) => void }) {
  const { t } = useTranslation();
  const name = (goal: string) => t(`agents.modes.goals.${goal as Goal}.name`);

  if (mode.needs_setup || mode.goal === null) {
    return <ListRow icon={Compass} tone="info" title={t('agents.modes.setupPrompt.title')} subtitle={t('agents.modes.setupPrompt.body')} onPress={() => router.push('/agent/setup')} />;
  }

  const suggestion = mode.suggestion;
  const kind = suggestion?.goal === 'lead' ? 'lead' : 'grow';

  return (
    <>
      <Chip icon={Compass} label={t('agents.modes.badge', { goal: name(mode.goal) })} onPress={() => router.push('/agent/goal')} />
      {suggestion ? (
        <Card style={{ gap: 8 }}>
          <Text variant="title">{t(`agents.modes.suggestion.${kind}.title`)}</Text>
          <Text>{t(`agents.modes.suggestion.${kind}.body`, { count: suggestion.closings })}</Text>
          <Text tone="muted">{t('agents.modes.suggestion.note')}</Text>
          <Button icon={Sparkles} label={t('agents.modes.suggestion.switch', { goal: name(suggestion.goal) })} onPress={() => void setGoal(suggestion.goal as Goal, 'suggested').then(onChange)} />
          <Button variant="secondary" label={t('agents.modes.suggestion.notYet')} onPress={() => void snooze().then(onChange)} />
        </Card>
      ) : null}
    </>
  );
}
