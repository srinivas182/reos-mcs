import * as Haptics from 'expo-haptics';
import type { ReactNode } from 'react';
import { ActivityIndicator, Pressable, View, type PressableProps } from 'react-native';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';

import { Text } from './Text';

export type ButtonVariant = 'primary' | 'secondary' | 'outline' | 'ghost' | 'destructive';
export type ButtonSize = 'default' | 'large';

export type ButtonProps = Omit<PressableProps, 'children'> & {
  /** Verb first: "Book session", not "Submit". */
  label: string;
  variant?: ButtonVariant;
  /** `large` (56 pt) for the one main action on a screen; `default` is 48 pt. */
  size?: ButtonSize;
  icon?: ReactNode;
  loading?: boolean;
  fullWidth?: boolean;
};

/**
 * The app's button: at least 48 × 48 points, a light haptic tap on press, a
 * spinner while loading (and announced as busy), and a clear pressed state.
 */
export function Button({ label, variant = 'primary', size = 'default', icon, loading = false, fullWidth = false, disabled, onPress, ...props }: ButtonProps) {
  const { theme } = useUnistyles();
  // Unistyles reserves the variant name `default`, so the default size is "no size variant".
  styles.useVariants({ variant, size: size === 'large' ? 'large' : undefined });
  const inactive = disabled || loading;
  const textTone = variant === 'primary' ? 'onAction' : variant === 'destructive' ? 'onAction' : variant === 'ghost' || variant === 'outline' ? 'action' : 'default';

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={label}
      accessibilityState={{ disabled: !!inactive, busy: loading }}
      disabled={inactive}
      hitSlop={8}
      onPress={(event) => {
        void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
        onPress?.(event);
      }}
      style={({ pressed }) => [styles.button, fullWidth && styles.fullWidth, pressed && styles.pressed, inactive && styles.inactive]}
      {...props}
    >
      {loading ? (
        <ActivityIndicator color={variant === 'primary' || variant === 'destructive' ? theme.colors.onAction : theme.colors.action} />
      ) : (
        <View style={styles.content}>
          {icon}
          <Text variant={size === 'large' ? 'bodyLarge' : 'body'} weight="semibold" tone={textTone} style={variant === 'destructive' ? styles.onDanger : undefined}>
            {label}
          </Text>
        </View>
      )}
    </Pressable>
  );
}

const styles = StyleSheet.create((theme) => ({
  button: {
    minHeight: theme.touch,
    minWidth: theme.touch,
    borderRadius: theme.radius.md,
    paddingHorizontal: theme.space.xl,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: 'transparent',
    variants: {
      variant: {
        primary: { backgroundColor: theme.colors.action },
        secondary: { backgroundColor: theme.colors.surfaceMuted },
        outline: { backgroundColor: theme.colors.surface, borderColor: theme.colors.borderControl },
        ghost: { backgroundColor: 'transparent' },
        destructive: { backgroundColor: theme.colors.danger },
      },
      size: {
        large: { minHeight: 56, paddingHorizontal: theme.space.xxl },
      },
    },
  },
  content: { flexDirection: 'row', alignItems: 'center', gap: theme.space.sm },
  fullWidth: { alignSelf: 'stretch' },
  pressed: { opacity: 0.85, transform: [{ scale: 0.99 }] },
  inactive: { opacity: 0.5 },
  onDanger: { color: theme.colors.onDanger },
}));
