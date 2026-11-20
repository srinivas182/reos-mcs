import { Pressable, View } from 'react-native';
import { StyleSheet } from 'react-native-unistyles';

import { Text } from './Text';

export type ChoiceTile<T extends string> = { value: T; label: string; description?: string };

export type ChoiceTilesProps<T extends string> = {
  label: string;
  value: T;
  options: readonly ChoiceTile<T>[];
  onChange: (value: T) => void;
};

/**
 * One choice from a few, shown as large tiles. Screen readers hear a radio
 * group: "Large, radio button, selected, 2 of 3".
 */
export function ChoiceTiles<T extends string>({ label, value, options, onChange }: ChoiceTilesProps<T>) {
  return (
    <View style={styles.group}>
      <Text weight="semibold" heading>
        {label}
      </Text>
      <View accessibilityRole="radiogroup" accessibilityLabel={label} style={styles.tiles}>
        {options.map((option) => {
          const selected = option.value === value;

          return (
            <Pressable
              key={option.value}
              accessibilityRole="radio"
              accessibilityState={{ checked: selected }}
              accessibilityLabel={option.description ? `${option.label}. ${option.description}` : option.label}
              onPress={() => onChange(option.value)}
              style={({ pressed }) => [styles.tile, selected && styles.selected, pressed && styles.pressed]}
            >
              <Text weight="semibold">{option.label}</Text>
              {option.description ? (
                <Text variant="caption" tone="muted">
                  {option.description}
                </Text>
              ) : null}
            </Pressable>
          );
        })}
      </View>
    </View>
  );
}

const styles = StyleSheet.create((theme) => ({
  group: { gap: theme.space.md },
  tiles: { gap: theme.space.md },
  tile: {
    minHeight: theme.touch,
    borderRadius: theme.radius.md,
    borderWidth: 2,
    borderColor: theme.colors.border,
    backgroundColor: theme.colors.surface,
    padding: theme.space.lg,
    gap: theme.space.xs,
  },
  selected: { borderColor: theme.colors.action, backgroundColor: theme.colors.actionTint },
  pressed: { opacity: 0.85 },
}));
