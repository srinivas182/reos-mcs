import { Redirect, router } from 'expo-router';
import { ArrowLeft, Home, ListChecks, MessageSquare, Shapes, Target } from 'lucide-react-native';

import { IconButton, ListRow, Screen, ScreenHeader, Text } from '@/ui';

/**
 * Development-only gallery: the kit, plus four candidate journey screens
 * rebuilt with it, for side-by-side review against the journey PDFs. Change
 * theme, text size and language in Display settings and come back.
 */
export default function DesignIndex() {
  if (!__DEV__) return <Redirect href="/" />;

  return (
    <Screen>
      <IconButton icon={ArrowLeft} label="Back" onPress={() => router.back()} />
      <ScreenHeader overline="Development only" title="Component gallery" />
      <Text tone="muted">Journey screens rebuilt with the mobile kit (candidate journey, screens 02, 07, 11 and 13).</Text>
      <ListRow icon={Home} title="Today" subtitle="Readiness ring, continue card, today's list, Ask bar, tabs" onPress={() => router.push('/design/today')} />
      <ListRow icon={Target} title="Practice" subtitle="Smart drill hero, feature tiles, list rows" onPress={() => router.push('/design/practice')} />
      <ListRow icon={ListChecks} title="Quiz" subtitle="Progress bar, lettered options, feedback callout" onPress={() => router.push('/design/quiz')} />
      <ListRow icon={MessageSquare} title="Ask" subtitle="Suggestions, chat bubbles, tutor nudge" onPress={() => router.push('/design/ask')} />
      <ListRow icon={Shapes} tone="neutral" title="All components" subtitle="Buttons, chips, pills, fields, toggles, sheet, undo, tabs for every role" onPress={() => router.push('/design/kit')} />
    </Screen>
  );
}
