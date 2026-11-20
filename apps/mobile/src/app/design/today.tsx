import { Bell, Calculator, Copy, Flame, Play } from 'lucide-react-native';
import { View } from 'react-native';
import { StyleSheet } from 'react-native-unistyles';

import { ActionCard, AskBar, Avatar, Chip, HeroCard, Icon, IconButton, ListRow, ProgressRing, Screen, ScreenHeader, SectionTitle, TabBar, Text } from '@/ui';

/** Candidate journey screen 07 "Today", rebuilt with the kit (development gallery). */
export default function TodayDemo() {
  return (
    <Screen
      footer={<AskBar placeholder="Ask anything about the exam" onPress={() => undefined} onVoice={() => undefined} />}
      tabBar={<TabBar role="candidate" active="today" onSelect={() => undefined} />}
    >
      <ScreenHeader
        overline="Saturday, Nov 14"
        title="Hi Sofia"
        trailing={
          <>
            <Chip tone="warning" icon={Flame} label="12" accessibilityLabel="12-day study streak" />
            <IconButton icon={Bell} label="Notifications" onPress={() => undefined} />
            <Avatar name="Sofia Reyes" onPress={() => undefined} />
          </>
        }
      />
      <HeroCard style={styles.row}>
        <ProgressRing value={68} label="Exam readiness 68 percent">
          <Text variant="title" style={styles.heroText}>
            68%
          </Text>
          <Text variant="caption" style={styles.heroMuted}>
            ready
          </Text>
        </ProgressRing>
        <View style={styles.flex}>
          <Text variant="caption" weight="bold" style={styles.eyebrow}>
            EXAM READINESS
          </Text>
          <Text variant="lead" style={styles.heroText}>
            98 days to your exam
          </Text>
          <Text style={styles.heroMuted}>Up 6 points this week. Math is your fastest win.</Text>
        </View>
      </HeroCard>
      <ActionCard accessibilityLabel="Continue: Contracts, the option period. Lesson 4, 6 minutes left" onPress={() => undefined}>
        <View style={styles.flex}>
          <Text variant="caption" weight="bold" style={styles.onActionMuted}>
            CONTINUE · COURSE 3 OF 6
          </Text>
          <Text variant="lead" tone="onAction">
            Contracts: the option period
          </Text>
          <Text style={styles.onActionMuted}>Lesson 4 · 6 min left</Text>
        </View>
        <View style={styles.play}>
          <Icon icon={Play} color="action" size={26} filled />
        </View>
      </ActionCard>
      <SectionTitle>{"Today's 20 minutes"}</SectionTitle>
      <ListRow icon={Calculator} title="Math lab: prorations" subtitle="8 min · your weakest topic" trailing={<Chip label="Next" onPress={() => undefined} />} />
      <ListRow icon={Copy} tone="success" title="10 flashcards: agency" subtitle="4 min · keep it fresh" />
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  row: { flexDirection: 'row', alignItems: 'center', gap: theme.space.lg },
  flex: { flex: 1, gap: 2 },
  eyebrow: { color: theme.colors.heroAccent, letterSpacing: 1 },
  heroText: { color: theme.colors.heroText },
  heroMuted: { color: theme.colors.heroMuted },
  onActionMuted: { color: theme.colors.onAction, opacity: 0.9 },
  play: { width: 56, height: 56, borderRadius: 999, backgroundColor: theme.colors.surface, alignItems: 'center', justifyContent: 'center' },
}));
