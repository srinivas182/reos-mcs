import { ArrowRight, BookOpen, Trash2 } from 'lucide-react-native';
import { useState } from 'react';
import { View } from 'react-native';
import { StyleSheet } from 'react-native-unistyles';

import type { AppRole, TabKey } from '@/navigation/roleTabs';
import {
  Button,
  Chip,
  EmptyState,
  PillGroup,
  ProgressBar,
  Screen,
  ScreenHeader,
  SectionTitle,
  Sheet,
  SwitchRow,
  TabBar,
  Text,
  TextField,
  useUndoToast,
} from '@/ui';

const roles: readonly AppRole[] = ['candidate', 'agent', 'broker', 'provider', 'instructor', 'mentor'];
const firstTab: Record<AppRole, TabKey> = { candidate: 'today', agent: 'today', broker: 'approvals', provider: 'messages', instructor: 'classes', mentor: 'sessions' };

/** Every mobile kit component in one scroll (development gallery). */
export default function KitDemo() {
  const undoToast = useUndoToast();
  const [sheetOpen, setSheetOpen] = useState(false);
  const [archived, setArchived] = useState(false);
  const [days, setDays] = useState<'3' | '5' | '7'>('5');
  const [reminders, setReminders] = useState(true);

  return (
    <Screen>
      <ScreenHeader overline="Development only" title="Mobile kit" />

      <SectionTitle>Buttons</SectionTitle>
      <Button label="Take the free 2-minute quiz" trailingIcon={ArrowRight} fullWidth />
      <Button label="Browse courses" icon={BookOpen} variant="secondary" fullWidth />
      <Button label="Ghost action" variant="ghost" />
      <Button label="Delete account" icon={Trash2} variant="destructive" />
      <Button label="Saving" loading />

      <SectionTitle>Chips</SectionTitle>
      <View style={styles.wrap}>
        <Chip tone="success" label="On track" />
        <Chip tone="warning" label="Due in 30 days" />
        <Chip tone="danger" label="Overdue" />
        <Chip tone="info" label="New" />
        <Chip label="4 of 10" />
      </View>

      <SectionTitle>Pills and progress</SectionTitle>
      <PillGroup label="Study days a week" value={days} onChange={setDays} options={[{ value: '3', label: '3 days' }, { value: '5', label: '5 days' }, { value: '7', label: 'Every day' }]} />
      <ProgressBar value={72} label="CE hours" color="success" />

      <SectionTitle>Fields and switches</SectionTitle>
      <TextField label="TREC licence number" hint="Seven digits, on your licence card" keyboardType="number-pad" />
      <TextField label="Email address" defaultValue="sofia@" error="Enter a full email address, like sofia@example.com." />
      <SwitchRow label="Study reminders" description="A nudge at 7pm if you haven't studied" value={reminders} onValueChange={setReminders} />

      <SectionTitle>Undo instead of “Are you sure?”</SectionTitle>
      <Text>Maria Gomez · {archived ? 'Archived' : 'Active client'}</Text>
      <Button
        label="Archive contact"
        variant="secondary"
        disabled={archived}
        onPress={() => {
          setArchived(true);
          undoToast({ message: 'Contact archived', onUndo: () => setArchived(false), undoneMessage: 'Contact restored' });
        }}
      />

      <SectionTitle>Empty state and sheet</SectionTitle>
      <EmptyState
        title="Your contacts live here"
        description="Connect Follow Up Boss or kvCORE and your contacts appear here, kept in sync both ways."
        action={<Button label="Connect your CRM" fullWidth onPress={() => setSheetOpen(true)} />}
      />
      <Sheet open={sheetOpen} onClose={() => setSheetOpen(false)} title="Connect your CRM">
        <Text>CRM connections arrive in Sprint 7. This shows the bottom-sheet pattern.</Text>
        <Button label="Got it" fullWidth onPress={() => setSheetOpen(false)} />
      </Sheet>

      <SectionTitle>Tabs for every role</SectionTitle>
      {roles.map((role) => (
        <View key={role} style={styles.tabs}>
          <Text variant="caption" tone="muted" weight="bold">
            {role.toUpperCase()}
          </Text>
          <TabBar role={role} active={firstTab[role]} onSelect={() => undefined} badges={role === 'broker' ? { approvals: 3 } : {}} />
        </View>
      ))}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  wrap: { flexDirection: 'row', flexWrap: 'wrap', gap: theme.space.sm },
  tabs: { gap: theme.space.xs, marginHorizontal: -theme.m.gutter },
}));
