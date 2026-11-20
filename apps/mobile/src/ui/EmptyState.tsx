import type { ReactNode } from 'react';
import { View } from 'react-native';
import { StyleSheet } from 'react-native-unistyles';

import { Text } from './Text';

export type EmptyStateProps = {
  /** What this place is for: "Your contacts live here". */
  title: string;
  /** One or two sentences that teach the next step. */
  description: string;
  icon?: ReactNode;
  /** The one main action (a Button). */
  action?: ReactNode;
  secondaryAction?: ReactNode;
};

/** Shown when a list has nothing in it yet. Teaches the page and offers exactly one main next step. */
export function EmptyState({ title, description, icon, action, secondaryAction }: EmptyStateProps) {
  return (
    <View accessibilityLabel={title} style={styles.box}>
      {icon ? <View style={styles.icon}>{icon}</View> : null}
      <Text variant="title" style={styles.center}>
        {title}
      </Text>
      <Text tone="muted" style={styles.center}>
        {description}
      </Text>
      {action || secondaryAction ? (
        <View style={styles.actions}>
          {action}
          {secondaryAction}
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create((theme) => ({
  box: {
    alignItems: 'center',
    gap: theme.space.md,
    padding: theme.space.xl,
    borderRadius: theme.radius.lg,
    borderWidth: 1,
    borderStyle: 'dashed',
    borderColor: theme.colors.border,
    backgroundColor: theme.colors.surface,
  },
  icon: {
    width: 56,
    height: 56,
    borderRadius: theme.radius.pill,
    backgroundColor: theme.colors.actionTint,
    alignItems: 'center',
    justifyContent: 'center',
  },
  center: { textAlign: 'center' },
  actions: { alignSelf: 'stretch', gap: theme.space.sm, marginTop: theme.space.sm },
}));
