import { useId } from 'react';
import { TextInput, View, type TextInputProps } from 'react-native';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';

import { Text } from './Text';

export type TextFieldProps = TextInputProps & {
  /** Always visible above the field (never placeholder-only). */
  label: string;
  /** Extra guidance under the label. */
  hint?: string;
  /** What's wrong and how to fix it: "Enter a full email address, like sofia@example.com." */
  error?: string;
};

/** A labelled 48-point text field whose label, hint and error are all read by screen readers. */
export function TextField({ label, hint, error, style, ...props }: TextFieldProps) {
  const { theme } = useUnistyles();
  const id = useId();
  styles.useVariants({ invalid: Boolean(error) });
  const description = [hint, error].filter(Boolean).join('. ');

  return (
    <View style={styles.field}>
      <Text nativeID={`${id}-label`} weight="medium">
        {label}
      </Text>
      {hint ? (
        <Text variant="caption" tone="muted">
          {hint}
        </Text>
      ) : null}
      <TextInput
        accessibilityLabel={label}
        accessibilityLabelledBy={`${id}-label`}
        accessibilityHint={description || undefined}
        aria-invalid={Boolean(error)}
        placeholderTextColor={theme.colors.textMuted}
        maxFontSizeMultiplier={2}
        style={[styles.input, style]}
        {...props}
      />
      {error ? (
        <Text variant="caption" tone="danger" weight="medium" accessibilityLiveRegion="polite" accessibilityRole="alert">
          {error}
        </Text>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create((theme) => ({
  field: { gap: theme.space.xs },
  input: {
    minHeight: theme.touch,
    borderRadius: theme.m.radius.option,
    borderWidth: 1,
    borderColor: theme.colors.borderControl,
    backgroundColor: theme.colors.surface,
    paddingHorizontal: theme.space.lg,
    color: theme.colors.text,
    fontSize: theme.font(theme.type.size.body),
    variants: {
      invalid: {
        true: { borderColor: theme.colors.danger, borderWidth: 2 },
        false: {},
      },
    },
  },
}));
