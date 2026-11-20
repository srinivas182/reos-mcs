import { Calculator, Clock, Copy, Headphones, User, Zap } from 'lucide-react-native';
import { View } from 'react-native';
import { StyleSheet } from 'react-native-unistyles';

import { Button, FeatureTile, HeroCard, ListRow, Screen, ScreenHeader, TabBar, Text } from '@/ui';

/** Candidate journey screen 11 "Practice", rebuilt with the kit (development gallery). */
export default function PracticeDemo() {
  return (
    <Screen tabBar={<TabBar role="candidate" active="practice" onSelect={() => undefined} />}>
      <ScreenHeader title="Practice" />
      <HeroCard>
        <Text variant="caption" weight="bold" style={styles.eyebrow}>
          SMART DRILL · PICKED FOR YOU
        </Text>
        <Text variant="lead" style={styles.heroText}>
          15 questions on your 3 weakest topics
        </Text>
        <Text style={styles.heroMuted}>Math · Finance · Contracts · 9 min</Text>
        <Button label="Start drill" variant="onHero" fullWidth onPress={() => undefined} />
      </HeroCard>
      <View style={styles.grid}>
        <FeatureTile icon={Copy} tone="success" title="Flashcards" subtitle="Swipe, 2 min" onPress={() => undefined} />
        <FeatureTile icon={Zap} tone="warning" title="Speed round" subtitle="60 seconds, beat your best" onPress={() => undefined} />
      </View>
      <View style={styles.grid}>
        <FeatureTile icon={Calculator} title="Math lab" subtitle="Step-by-step hints" onPress={() => undefined} />
        <FeatureTile icon={Headphones} tone="neutral" title="Drive mode" subtitle="Voice quiz, hands free" onPress={() => undefined} />
      </View>
      <ListRow icon={Clock} title="Full exam simulation" subtitle="National + Texas · real format and timing" onPress={() => undefined} />
      <ListRow icon={User} tone="neutral" title="Challenge a friend" subtitle="Same 10 questions, who wins?" onPress={() => undefined} />
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  grid: { flexDirection: 'row', gap: theme.space.md },
  eyebrow: { color: theme.colors.heroAccent, letterSpacing: 1 },
  heroText: { color: theme.colors.heroText },
  heroMuted: { color: theme.colors.heroMuted, marginBottom: theme.space.sm },
}));
