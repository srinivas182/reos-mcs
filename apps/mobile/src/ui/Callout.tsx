import type { ReactNode } from 'react';
import { View } from 'react-native';
import { StyleSheet } from 'react-native-unistyles';

import { Text } from './Text';
import { toneColors, withAlpha, type Tone } from './tones';

const calloutFill = {
  success: 'successSurface',
  warning: 'warningSurface',
  danger: 'dangerSurface',
  info: 'actionTint',
  neutral: 'surfaceMuted',
} as const;

type CalloutProps = {
  tone?: Tone;
  title: string;
  body?: string;
  /** An optional button on the right ("Book"). */
  action?: ReactNode;
  /** Announce when it appears (quiz feedback). */
  live?: boolean;
};

/** A tinted box with a bold line and a short explanation: quiz feedback, nudges, notices. */
export function Callout({ tone = 'info', title, body, action, live = false }: CalloutProps) {
  const { fg } = toneColors[tone];

  return (
    <View accessibilityLiveRegion={live ? 'polite' : 'none'} style={styles.box(tone, fg)}>
      <View accessible style={styles.text}>
        <Text weight="bold" style={styles.color(fg)}>
          {title}
        </Text>
        {body ? <Text style={styles.color(fg)}>{body}</Text> : null}
      </View>
      {action}
    </View>
  );
}

const styles = StyleSheet.create((theme) => ({
  // Pale fill with a light border of the same hue, as in the journeys.
  box: (tone: Tone, fg: keyof typeof theme.colors) => ({
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.space.md,
    padding: theme.space.lg,
    borderRadius: theme.m.radius.card,
    borderWidth: 1,
    borderColor: withAlpha(theme.colors[fg], 0.25),
    backgroundColor: theme.colors[calloutFill[tone]],
  }),
  text: { flex: 1, gap: theme.space.xs },
  color: (fg: keyof typeof theme.colors) => ({ color: theme.colors[fg] }),
}));
