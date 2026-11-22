import { initialsOf } from '@/lib/personName';
import type { LucideIcon } from 'lucide-react-native';
import type { ReactNode } from 'react';
import { Pressable, View } from 'react-native';
import { StyleSheet } from 'react-native-unistyles';

import { Icon } from './Icon';
import { Text } from './Text';

/** A screen title with an optional line above (date, step) and items on the right (streak chip, bell, avatar). */
export function ScreenHeader({ overline, title, trailing }: { overline?: string; title: string; trailing?: ReactNode }) {
  return (
    <View style={styles.header}>
      <View style={styles.titles}>
        {overline ? <Text tone="muted">{overline}</Text> : null}
        <Text variant="headline" style={styles.title}>
          {title}
        </Text>
      </View>
      {trailing ? <View style={styles.trailing}>{trailing}</View> : null}
    </View>
  );
}

/** Initials in a tinted circle (48 pt). Becomes a button when `onPress` is given. */
export function Avatar({ name, label, onPress }: { name: string; label?: string; onPress?: () => void }) {
  const initials = initialsOf(name);

  return (
    <Pressable accessibilityRole={onPress ? 'button' : 'image'} accessibilityLabel={label ?? name} disabled={!onPress} onPress={onPress} style={styles.avatar}>
      <Text weight="bold" style={styles.initials}>
        {initials}
      </Text>
    </Pressable>
  );
}

/** A round 48-pt icon button with a label for screen readers (bell, settings, back); `primary` fills it blue (the + on My licences). */
export function IconButton({ icon, label, onPress, primary = false }: { icon: LucideIcon; label: string; onPress: () => void; primary?: boolean }) {
  return (
    <Pressable accessibilityRole="button" accessibilityLabel={label} onPress={onPress} style={({ pressed }) => [styles.iconButton, primary && styles.primary, pressed && styles.pressed]}>
      <Icon icon={icon} color={primary ? 'onAction' : 'text'} />
    </Pressable>
  );
}

/** Section heading inside a screen: "Today's 20 minutes". */
export function SectionTitle({ children }: { children: string }) {
  return (
    <Text variant="lead" heading style={styles.section}>
      {children}
    </Text>
  );
}

const styles = StyleSheet.create((theme) => ({
  header: { flexDirection: 'row', alignItems: 'center', gap: theme.space.md },
  titles: { flex: 1 },
  title: { letterSpacing: -0.5 },
  trailing: { flexDirection: 'row', alignItems: 'center', gap: theme.space.sm },
  avatar: {
    width: theme.m.avatar,
    height: theme.m.avatar,
    borderRadius: theme.m.radius.pill,
    backgroundColor: theme.colors.toneInfoBg,
    alignItems: 'center',
    justifyContent: 'center',
  },
  initials: { color: theme.colors.toneInfoFg },
  iconButton: {
    width: theme.touch,
    height: theme.touch,
    borderRadius: theme.m.radius.pill,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: theme.colors.surface,
    alignItems: 'center',
    justifyContent: 'center',
  },
  primary: { backgroundColor: theme.colors.action, borderColor: theme.colors.action },
  section: { marginTop: theme.space.sm },
  pressed: { opacity: 0.85 },
}));
