import type { LucideIcon } from 'lucide-react-native';
import { ChevronRight } from 'lucide-react-native';
import type { ReactNode } from 'react';
import { Pressable, Switch, View } from 'react-native';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';

import { Icon } from './Icon';
import { Text } from './Text';
import { toneColors, type Tone } from './tones';

/** The rounded, tinted icon square used in list rows and feature tiles (44 pt). */
export function IconTile({ icon, tone = 'info' }: { icon: LucideIcon; tone?: Tone }) {
  const { bg, fg } = toneColors[tone];

  return (
    <View style={styles.tile(bg)}>
      <Icon icon={icon} color={fg} size={22} />
    </View>
  );
}

type ListRowProps = {
  icon: LucideIcon;
  tone?: Tone;
  title: string;
  subtitle?: string;
  /** A chip, button or nothing. A chevron is shown automatically for tappable rows without one. */
  trailing?: ReactNode;
  onPress?: () => void;
};

/** A card row: icon tile, title and subtitle, then a chip, button or arrow. */
export function ListRow({ icon, tone, title, subtitle, trailing, onPress }: ListRowProps) {
  const body = (
    <>
      <IconTile icon={icon} tone={tone} />
      <View style={styles.text}>
        <Text variant="bodyLarge" weight="semibold">
          {title}
        </Text>
        {subtitle ? <Text tone="muted">{subtitle}</Text> : null}
      </View>
      {trailing ?? (onPress ? <Icon icon={ChevronRight} color="textMuted" /> : null)}
    </>
  );

  if (onPress) {
    return (
      <Pressable
        accessibilityRole="button"
        accessibilityLabel={subtitle ? `${title}. ${subtitle}` : title}
        onPress={onPress}
        style={({ pressed }) => [styles.row, pressed && styles.pressed]}
      >
        {body}
      </Pressable>
    );
  }

  return (
    <View accessible accessibilityLabel={subtitle ? `${title}. ${subtitle}` : title} style={styles.row}>
      {body}
    </View>
  );
}

/** A square-ish card in a two-column grid: "Flashcards · Swipe, 2 min". */
export function FeatureTile({ icon, tone, title, subtitle, onPress }: { icon: LucideIcon; tone?: Tone; title: string; subtitle: string; onPress: () => void }) {
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={`${title}. ${subtitle}`}
      onPress={onPress}
      style={({ pressed }) => [styles.feature, pressed && styles.pressed]}
    >
      <IconTile icon={icon} tone={tone} />
      <Text variant="bodyLarge" weight="semibold">
        {title}
      </Text>
      <Text tone="muted">{subtitle}</Text>
    </Pressable>
  );
}

/** A labelled on/off switch row (notification and privacy settings). */
export function SwitchRow({
  label,
  description,
  value,
  onValueChange,
  disabled = false,
}: {
  label: string;
  description?: string;
  value: boolean;
  onValueChange: (value: boolean) => void;
  /** Can't be changed now (a locked kind, or texts not turned on yet). */
  disabled?: boolean;
}) {
  const { theme } = useUnistyles();

  return (
    <View style={styles.switchRow}>
      <View style={styles.text}>
        <Text weight="medium">{label}</Text>
        {description ? (
          <Text variant="caption" tone="muted">
            {description}
          </Text>
        ) : null}
      </View>
      <Switch
        accessibilityLabel={label}
        accessibilityHint={description}
        value={value}
        onValueChange={onValueChange}
        disabled={disabled}
        accessibilityState={{ disabled, checked: value }}
        trackColor={{ true: theme.colors.action, false: theme.colors.borderControl }}
        thumbColor={theme.colors.surface}
        ios_backgroundColor={theme.colors.borderControl}
      />
    </View>
  );
}

const styles = StyleSheet.create((theme) => ({
  tile: (bg: keyof typeof theme.colors) => ({
    width: theme.m.iconTile,
    height: theme.m.iconTile,
    borderRadius: theme.m.radius.tile,
    backgroundColor: theme.colors[bg],
    alignItems: 'center',
    justifyContent: 'center',
  }),
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.space.md,
    minHeight: theme.touch + 24,
    paddingHorizontal: theme.space.lg,
    paddingVertical: theme.space.md,
    backgroundColor: theme.colors.surface,
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.m.radius.card,
  },
  text: { flex: 1, gap: 2 },
  feature: {
    flex: 1,
    gap: theme.space.sm,
    padding: theme.space.lg,
    backgroundColor: theme.colors.surface,
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.m.radius.card,
  },
  switchRow: { flexDirection: 'row', alignItems: 'center', gap: theme.space.md, minHeight: theme.touch + 8 },
  pressed: { opacity: 0.85 },
}));
