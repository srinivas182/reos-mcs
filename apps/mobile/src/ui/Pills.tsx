import { Pressable, View } from 'react-native';
import { StyleSheet } from 'react-native-unistyles';

import { Text } from './Text';

export type PillOption<T extends string> = { value: T; label: string };

type PillGroupProps<T extends string> = {
  /** Read by screen readers as the group's name. */
  label: string;
  value: T;
  options: readonly PillOption<T>[];
  onChange: (value: T) => void;
};

/**
 * One choice from a short list, as pills that wrap. The chosen pill fills navy
 * (as in the journeys: study days, exam month, filters). Screen readers hear a
 * radio group.
 */
export function PillGroup<T extends string>({ label, value, options, onChange }: PillGroupProps<T>) {
  return (
    <View accessibilityRole="radiogroup" accessibilityLabel={label} style={styles.wrap}>
      {options.map((option) => {
        const selected = option.value === value;

        return (
          <Pressable
            key={option.value}
            accessibilityRole="radio"
            accessibilityLabel={option.label}
            accessibilityState={{ checked: selected }}
            onPress={() => onChange(option.value)}
            style={({ pressed }) => [styles.pill, selected && styles.selected, pressed && styles.pressed]}
          >
            <Text weight="semibold" style={selected ? styles.selectedText : undefined}>
              {option.label}
            </Text>
          </Pressable>
        );
      })}
    </View>
  );
}

type SuggestionPillsProps = {
  /** Read by screen readers before the suggestions. */
  label: string;
  suggestions: readonly { label: string; onPress: () => void }[];
};

/** Outlined pills that start an action: "Explain a term", "Quiz me". */
export function SuggestionPills({ label, suggestions }: SuggestionPillsProps) {
  return (
    <View accessibilityLabel={label} style={styles.wrap}>
      {suggestions.map((suggestion) => (
        <Pressable
          key={suggestion.label}
          accessibilityRole="button"
          accessibilityLabel={suggestion.label}
          onPress={suggestion.onPress}
          style={({ pressed }) => [styles.pill, pressed && styles.pressed]}
        >
          <Text weight="semibold">{suggestion.label}</Text>
        </Pressable>
      ))}
    </View>
  );
}

const styles = StyleSheet.create((theme) => ({
  wrap: { flexDirection: 'row', flexWrap: 'wrap', gap: theme.space.sm },
  pill: {
    minHeight: theme.touch,
    justifyContent: 'center',
    paddingHorizontal: theme.space.lg,
    borderRadius: theme.m.radius.pill,
    borderWidth: 1,
    borderColor: theme.colors.borderControl,
    backgroundColor: theme.colors.surface,
  },
  selected: { backgroundColor: theme.colors.text, borderColor: theme.colors.text },
  selectedText: { color: theme.colors.surface },
  pressed: { opacity: 0.85 },
}));
