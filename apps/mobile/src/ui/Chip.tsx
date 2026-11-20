import type { LucideIcon } from 'lucide-react-native';
import { Pressable, View } from 'react-native';
import { StyleSheet } from 'react-native-unistyles';

import { Icon } from './Icon';
import { Text } from './Text';
import { toneColors, type Tone } from './tones';

export type ChipProps = {
  label: string;
  tone?: Tone;
  icon?: LucideIcon;
  /** Makes the chip a button (for example "Next", "Try a similar one"). Pressable chips are 48 pt tall. */
  onPress?: () => void;
  accessibilityLabel?: string;
};

/** A small tinted label: status ("On track", "Due soon"), counts ("4 of 10"), or a light action. */
export function Chip({ label, tone = 'neutral', icon, onPress, accessibilityLabel }: ChipProps) {
  const { bg, fg } = toneColors[tone];
  const content = (
    <>
      {icon ? <Icon icon={icon} color={fg} size={16} /> : null}
      <Text variant="caption" weight="bold" style={styles.text(fg)}>
        {label}
      </Text>
    </>
  );

  if (onPress) {
    return (
      <Pressable
        accessibilityRole="button"
        accessibilityLabel={accessibilityLabel ?? label}
        onPress={onPress}
        style={({ pressed }) => [styles.chip(bg), styles.pressable, pressed && styles.pressed]}
      >
        {content}
      </Pressable>
    );
  }

  return (
    <View accessible accessibilityLabel={accessibilityLabel ?? label} style={styles.chip(bg)}>
      {content}
    </View>
  );
}

const styles = StyleSheet.create((theme) => ({
  chip: (bg: keyof typeof theme.colors) => ({
    flexDirection: 'row',
    alignItems: 'center',
    alignSelf: 'flex-start',
    gap: 6,
    paddingHorizontal: theme.space.md,
    paddingVertical: theme.space.xs,
    borderRadius: theme.m.radius.pill,
    backgroundColor: theme.colors[bg],
  }),
  pressable: { minHeight: theme.touch, paddingHorizontal: theme.space.lg },
  text: (fg: keyof typeof theme.colors) => ({ color: theme.colors[fg] }),
  pressed: { opacity: 0.8 },
}));
