import { Redirect } from 'expo-router';
import { useState } from 'react';
import { ScrollView } from 'react-native';
import { StyleSheet } from 'react-native-unistyles';

import { Button, EmptyState, Sheet, Text, TextField, useUndoToast } from '@/ui';

/**
 * Development-only gallery of every mobile component (the phone counterpart of
 * the web Storybook). Change theme, text size and language in Display settings
 * and come back to see everything adapt. Not reachable in release builds.
 */
export default function DesignGallery() {
  const undoToast = useUndoToast();
  const [sheetOpen, setSheetOpen] = useState(false);
  const [archived, setArchived] = useState(false);

  if (!__DEV__) return <Redirect href="/" />;

  return (
    <ScrollView contentContainerStyle={styles.screen}>
      <Text variant="headline">Component gallery</Text>

      <Text variant="lead">Text</Text>
      <Text variant="display">Display 40</Text>
      <Text variant="title">Title 24</Text>
      <Text>Body 16: Find a mentor, finish your CE, close more deals.</Text>
      <Text variant="caption" tone="muted">
        Caption 14: the smallest size anywhere.
      </Text>

      <Text variant="lead">Buttons</Text>
      <Button label="Start lesson" size="large" />
      <Button label="Book session" />
      <Button label="Secondary" variant="secondary" />
      <Button label="Outline" variant="outline" />
      <Button label="Ghost" variant="ghost" />
      <Button label="Delete account" variant="destructive" />
      <Button label="Saving" loading />

      <Text variant="lead">Fields</Text>
      <TextField label="TREC licence number" hint="Seven digits, on your licence card" keyboardType="number-pad" />
      <TextField label="Email address" defaultValue="sofia@" error="Enter a full email address, like sofia@example.com." />

      <Text variant="lead">Undo instead of “Are you sure?”</Text>
      <Text>Maria Gomez · {archived ? 'Archived' : 'Active client'}</Text>
      <Button
        label="Archive contact"
        variant="outline"
        disabled={archived}
        onPress={() => {
          setArchived(true);
          undoToast({ message: 'Contact archived', onUndo: () => setArchived(false), undoneMessage: 'Contact restored' });
        }}
      />

      <Text variant="lead">Empty state and sheet</Text>
      <EmptyState
        title="Your contacts live here"
        description="Connect Follow Up Boss or kvCORE and your contacts appear here, kept in sync both ways."
        action={<Button label="Connect your CRM" onPress={() => setSheetOpen(true)} />}
      />
      <Sheet open={sheetOpen} onClose={() => setSheetOpen(false)} title="Connect your CRM">
        <Text>CRM connections arrive in Sprint 7. This sheet shows the bottom-sheet pattern.</Text>
        <Button label="Got it" onPress={() => setSheetOpen(false)} />
      </Sheet>
    </ScrollView>
  );
}

const styles = StyleSheet.create((theme, rt) => ({
  screen: {
    gap: theme.space.lg,
    paddingTop: rt.insets.top + theme.space.lg,
    paddingBottom: rt.insets.bottom + theme.space.xxxl,
    paddingHorizontal: theme.space.xl,
    backgroundColor: theme.colors.page,
  },
}));
