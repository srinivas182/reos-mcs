import type { ReactNode } from 'react';
import { Pressable, View, type ViewProps } from 'react-native';
import { StyleSheet } from 'react-native-unistyles';

type CardProps = ViewProps & {
  children: ReactNode;
  /** Makes the whole card one button. Give it an `accessibilityLabel` that says where it goes. */
  onPress?: () => void;
};

/** White card, rounded 18, hairline border: the journeys' basic container. */
export function Card({ children, onPress, style, accessibilityLabel, ...props }: CardProps) {
  if (onPress) {
    return (
      <Pressable
        accessibilityRole="button"
        accessibilityLabel={accessibilityLabel}
        onPress={onPress}
        style={({ pressed }) => [styles.card, pressed && styles.pressed, style as object]}
      >
        {children}
      </Pressable>
    );
  }

  return (
    <View style={[styles.card, style]} accessibilityLabel={accessibilityLabel} {...props}>
      {children}
    </View>
  );
}

/** Dark navy card for the one thing that matters most on a screen (readiness, smart drill). */
export function HeroCard({ children, style, ...props }: ViewProps & { children: ReactNode }) {
  return (
    <View style={[styles.hero, style]} {...props}>
      {children}
    </View>
  );
}

/** Bright blue card for "continue where you left off". Tapping it continues. */
export function ActionCard({ children, onPress, accessibilityLabel }: { children: ReactNode; onPress: () => void; accessibilityLabel: string }) {
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={accessibilityLabel}
      onPress={onPress}
      style={({ pressed }) => [styles.action, pressed && styles.pressed]}
    >
      {children}
    </Pressable>
  );
}

const styles = StyleSheet.create((theme) => ({
  card: {
    backgroundColor: theme.colors.surface,
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.m.radius.card,
    paddingHorizontal: theme.space.lg,
    paddingVertical: theme.space.md + 2,
    gap: theme.space.sm,
  },
  hero: {
    backgroundColor: theme.colors.heroBg,
    borderRadius: theme.m.radius.hero,
    paddingHorizontal: theme.space.lg + 2,
    paddingVertical: theme.space.lg,
    gap: theme.space.sm,
  },
  action: {
    backgroundColor: theme.colors.action,
    borderRadius: theme.m.radius.hero,
    paddingHorizontal: theme.space.lg + 2,
    paddingVertical: theme.space.lg,
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.space.md,
  },
  pressed: { opacity: 0.9 },
}));
