import { useRef, type ComponentRef } from 'react';
import { Pressable, TextInput, View } from 'react-native';
import { StyleSheet } from 'react-native-unistyles';

import { Text } from './Text';

type CodeInputProps = {
  /** Read by screen readers: "Six-digit code". */
  label: string;
  value: string;
  onChange: (value: string) => void;
  length?: number;
  invalid?: boolean;
  autoFocus?: boolean;
};

/**
 * Six boxes for a texted code (journey screen "Enter the code"). One hidden
 * field underneath does the typing, so iOS and Android auto-fill the code
 * from Messages, and screen readers hear a single labelled field.
 */
export function CodeInput({ label, value, onChange, length = 6, invalid = false, autoFocus = true }: CodeInputProps) {
  const input = useRef<ComponentRef<typeof TextInput>>(null);
  styles.useVariants({ invalid });

  return (
    <Pressable onPress={() => input.current?.focus()} accessible={false} style={styles.row}>
      {Array.from({ length }, (_, index) => {
        const active = index === Math.min(value.length, length - 1);

        return (
          <View key={index} style={[styles.box, active && styles.active]} importantForAccessibility="no-hide-descendants" accessibilityElementsHidden>
            <Text variant="title" weight="bold">
              {value[index] ?? ''}
            </Text>
          </View>
        );
      })}
      <TextInput
        ref={input}
        accessibilityLabel={label}
        value={value}
        onChangeText={(text) => onChange(text.replace(/\D/g, '').slice(0, length))}
        keyboardType="number-pad"
        textContentType="oneTimeCode"
        autoComplete="sms-otp"
        maxLength={length}
        autoFocus={autoFocus}
        caretHidden
        style={styles.hidden}
      />
    </Pressable>
  );
}

const styles = StyleSheet.create((theme) => ({
  row: { flexDirection: 'row', gap: theme.space.sm, justifyContent: 'center' },
  box: {
    width: 48,
    height: 58,
    borderRadius: theme.m.radius.tile,
    borderWidth: 1,
    borderColor: theme.colors.borderControl,
    backgroundColor: theme.colors.surface,
    alignItems: 'center',
    justifyContent: 'center',
    variants: {
      invalid: { true: { borderColor: theme.colors.danger } },
    },
  },
  active: { borderWidth: 2, borderColor: theme.colors.action },
  // Covers the boxes so taps focus it; invisible but still focusable and fillable.
  hidden: { position: 'absolute', inset: 0, opacity: 0.011, color: 'transparent' },
}));
