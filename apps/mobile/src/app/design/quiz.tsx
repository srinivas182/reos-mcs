import { useState } from 'react';
import { View } from 'react-native';
import { StyleSheet } from 'react-native-unistyles';

import { Button, Callout, Chip, OptionGroup, ProgressBar, Screen, Text } from '@/ui';

const options = [
  { value: 'a', label: 'Keep it to themselves' },
  { value: 'b', label: 'Share it with the buyer, their client' },
  { value: 'c', label: "Tell the seller's agent first" },
  { value: 'd', label: 'Ask the broker to decide' },
] as const;

/** Candidate journey screen 02 "Free quiz", rebuilt with the kit (development gallery). */
export default function QuizDemo() {
  const [answer, setAnswer] = useState<string | null>('b');

  return (
    <Screen footer={<View style={styles.footer}><Button label="Next question" fullWidth onPress={() => setAnswer(null)} /></View>}>
      <View style={styles.top}>
        <Text tone="muted" weight="semibold" style={styles.flex}>
          Free quiz · no account needed
        </Text>
        <Chip label="4 of 10" />
      </View>
      <ProgressBar value={40} label="Quiz progress" />
      <Text variant="caption" weight="bold" tone="action" style={styles.eyebrow}>
        AGENCY
      </Text>
      <Text variant="title">{"A buyer's agent learns the seller will accept less. The buyer asks. What should the agent do?"}</Text>
      <OptionGroup label="Answers" lettered options={options} value={answer} onChange={setAnswer} correct={answer ? 'b' : undefined} />
      {answer === 'b' ? <Callout tone="success" live title="Correct, nicely done." body="A buyer's agent owes the buyer loyalty and disclosure of facts that matter to them." /> : null}
      {answer && answer !== 'b' ? <Callout tone="danger" live title="Not quite." body="A buyer's agent owes the buyer loyalty, so they share facts that matter to the buyer." /> : null}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  top: { flexDirection: 'row', alignItems: 'center', gap: theme.space.md },
  flex: { flex: 1 },
  eyebrow: { letterSpacing: 1, marginTop: theme.space.sm },
  footer: { paddingHorizontal: theme.m.gutter, paddingBottom: theme.space.xl },
}));
