import * as Haptics from 'expo-haptics';
import type { LucideIcon } from 'lucide-react-native';
import { ActivityIndicator, Pressable, View, type PressableProps } from 'react-native';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';

import type { SemanticColors } from '@reos/tokens';

import { Icon } from './Icon';
import { Text } from './Text';

/**
 * - `primary`: blue, 54 pt (the journeys' main button)
 * - `secondary`: white with a border, 50 pt
 * - `onHero`: white button on a dark hero card ("Start drill")
 * - `heroOutline`: outlined, on a dark screen ("I have a code or invite")
 * - `ghost`: text only
 * - `destructive`: red, for things that can't be undone
 * - `warning`: dark amber, the action inside an amber nudge ("Book" a tutor)
 */
export type ButtonVariant = 'primary' | 'secondary' | 'onHero' | 'heroOutline' | 'ghost' | 'destructive' | 'warning';

export type ButtonProps = Omit<PressableProps, 'children'> & {
  /** Verb first: "Book session", not "Submit". */
  label: string;
  variant?: ButtonVariant;
  icon?: LucideIcon;
  /** Icon after the label (for "Continue →"). */
  trailingIcon?: LucideIcon;
  loading?: boolean;
  /** Stretch to the full width (the default for main actions at the bottom of a screen). */
  fullWidth?: boolean;
};

const textColor: Record<ButtonVariant, keyof SemanticColors> = {
  primary: 'onAction',
  secondary: 'text',
  onHero: 'heroBg',
  heroOutline: 'heroText',
  ghost: 'action',
  destructive: 'onDanger',
  warning: 'toneWarningBg',
};

/** The journeys' button: 54 pt, rounded 16, a light haptic tap, busy state while loading. */
export function Button({ label, variant = 'primary', icon, trailingIcon, loading = false, fullWidth = false, disabled, onPress, ...props }: ButtonProps) {
  const { theme } = useUnistyles();
  styles.useVariants({ variant });
  const inactive = disabled || loading;
  const color = textColor[variant];

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={label}
      accessibilityState={{ disabled: !!inactive, busy: loading }}
      disabled={inactive}
      hitSlop={4}
      onPress={(event) => {
        void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
        onPress?.(event);
      }}
      style={({ pressed }) => [styles.button, fullWidth && styles.fullWidth, pressed && styles.pressed, inactive && styles.inactive]}
      {...props}
    >
      {loading ? (
        <ActivityIndicator color={theme.colors[color]} />
      ) : (
        <View style={styles.content}>
          {icon ? <Icon icon={icon} color={color} size={20} /> : null}
          <Text weight={variant === 'secondary' ? 'semibold' : 'bold'} style={styles.label(color)}>
            {label}
          </Text>
          {trailingIcon ? <Icon icon={trailingIcon} color={color} size={20} /> : null}
        </View>
      )}
    </Pressable>
  );
}

const styles = StyleSheet.create((theme) => ({
  button: {
    minHeight: theme.m.buttonHeight,
    borderRadius: theme.m.radius.button,
    paddingHorizontal: theme.space.xl,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: 'transparent',
    variants: {
      variant: {
        primary: { backgroundColor: theme.colors.action },
        secondary: { minHeight: theme.m.secondaryButtonHeight, backgroundColor: theme.colors.surface, borderColor: theme.colors.borderControl },
        onHero: { backgroundColor: theme.colors.heroText },
        heroOutline: { minHeight: theme.m.secondaryButtonHeight, backgroundColor: 'transparent', borderColor: theme.colors.heroMuted },
        ghost: { minHeight: theme.touch, backgroundColor: 'transparent', paddingHorizontal: theme.space.md },
        destructive: { backgroundColor: theme.colors.danger },
        warning: { minHeight: theme.touch, backgroundColor: theme.colors.toneWarningFg, borderRadius: theme.m.radius.pill, paddingHorizontal: theme.space.lg },
      },
    },
  },
  content: { flexDirection: 'row', alignItems: 'center', gap: theme.space.sm },
  label: (color: keyof SemanticColors) => ({ color: theme.colors[color] }),
  fullWidth: { alignSelf: 'stretch' },
  pressed: { opacity: 0.85, transform: [{ scale: 0.99 }] },
  inactive: { opacity: 0.5 },
}));
