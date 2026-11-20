import { View } from 'react-native';
import { StyleSheet } from 'react-native-unistyles';

import { AskBar, Button, Callout, ChatBubble, Chip, Screen, ScreenHeader, SuggestionPills, TabBar, Text } from '@/ui';

/** Candidate journey screen 13 "Ask", rebuilt with the kit (development gallery). */
export default function AskDemo() {
  return (
    <Screen
      footer={<AskBar placeholder="Ask, or snap a photo of a question" onPress={() => undefined} onVoice={() => undefined} />}
      tabBar={<TabBar role="candidate" active="ask" onSelect={() => undefined} />}
    >
      <ScreenHeader title="Ask" trailing={<Chip tone="info" label="Your study mate" />} />
      <SuggestionPills
        label="Suggestions"
        suggestions={[
          { label: 'Explain a term', onPress: () => undefined },
          { label: 'Solve a math problem', onPress: () => undefined },
          { label: 'Quiz me', onPress: () => undefined },
        ]}
      />
      <ChatBubble from="me">How do I prorate taxes if closing is June 15?</ChatBubble>
      <ChatBubble from="assistant" source="Texas Real Estate Math, lesson 7">
        <Text variant="bodyLarge" weight="bold">
          Three steps, using $3,650 a year:
        </Text>
        <Text variant="bodyLarge">1. Daily tax: $3,650 ÷ 365 = $10 a day</Text>
        <Text variant="bodyLarge">2. Seller owns Jan 1 to Jun 14: 165 days</Text>
        <Text variant="bodyLarge">3. Seller owes the buyer 165 × $10 = $1,650</Text>
        <Text tone="muted">Texas taxes are paid in arrears, so the seller credits the buyer.</Text>
      </ChatBubble>
      <View style={styles.row}>
        <Chip tone="info" label="Try a similar one" onPress={() => undefined} />
        <Chip label="Save to flashcards" onPress={() => undefined} />
      </View>
      <Callout tone="warning" title="Still stuck? Talk to a tutor." body="3 math tutors free tonight · from $35" action={<Button label="Book" variant="warning" onPress={() => undefined} />} />
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  row: { flexDirection: 'row', flexWrap: 'wrap', gap: theme.space.sm },
}));
