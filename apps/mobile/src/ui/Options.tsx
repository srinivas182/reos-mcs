import { Check, X } from 'lucide-react-native';
import { Pressable, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { Icon } from './Icon';
import { Text } from './Text';

export type Option<T extends string> = { value: T; label: string; description?: string };

type OptionGroupProps<T extends string> = {
  label: string;
  options: readonly Option<T>[];
  value: T | null;
  onChange: (value: T) => void;
  /** Show A, B, C, D badges (quizzes). */
  lettered?: boolean;
  /** After answering a quiz: the correct value. Marks right in green, a wrong pick in red, and locks the group. */
  correct?: T;
};

/**
 * A vertical list of answer or setting rows (the journeys' quiz options and
 * settings choices). Screen readers hear a radio group; after a quiz answer
 * they also hear "correct" or "incorrect".
 */
export function OptionGroup<T extends string>({ label, options, value, onChange, lettered = false, correct }: OptionGroupProps<T>) {
  const { t } = useTranslation();
  const answered = correct !== undefined && value !== null;

  return (
    <View accessibilityRole="radiogroup" accessibilityLabel={label} style={styles.list}>
      {options.map((option, index) => {
        const selected = option.value === value;
        const isRight = answered && option.value === correct;
        const isWrong = answered && selected && option.value !== correct;
        const state = isRight ? 'right' : isWrong ? 'wrong' : selected ? 'selected' : undefined;
        const result = isRight ? t('options.correct') : isWrong ? t('options.incorrect') : '';
        styles.useVariants({ state });

        return (
          <Pressable
            key={option.value}
            accessibilityRole="radio"
            accessibilityLabel={[lettered ? String.fromCharCode(65 + index) : '', option.label, option.description ?? '', result].filter(Boolean).join('. ')}
            accessibilityState={{ checked: selected, disabled: answered }}
            disabled={answered}
            onPress={() => onChange(option.value)}
            style={({ pressed }) => [styles.option, pressed && styles.pressed]}
          >
            {lettered ? (
              <View style={styles.letter}>
                <Text variant="caption" weight="bold" style={styles.letterText}>
                  {String.fromCharCode(65 + index)}
                </Text>
              </View>
            ) : null}
            <View style={styles.text}>
              <Text variant="bodyLarge">{option.label}</Text>
              {option.description ? <Text tone="muted">{option.description}</Text> : null}
            </View>
            {isRight || (!answered && selected) ? <Icon icon={Check} color={isRight ? 'toneSuccessFg' : 'action'} /> : null}
            {isWrong ? <Icon icon={X} color="toneDangerFg" /> : null}
          </Pressable>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create((theme) => ({
  list: { gap: theme.space.md },
  option: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.space.md,
    minHeight: theme.touch + 4,
    paddingHorizontal: theme.space.lg,
    paddingVertical: theme.space.md,
    borderRadius: theme.m.radius.option,
    borderWidth: 1.5,
    borderColor: theme.colors.border,
    backgroundColor: theme.colors.surface,
    variants: {
      state: {
        selected: { borderColor: theme.colors.action, backgroundColor: theme.colors.actionTint },
        right: { borderColor: theme.colors.success, backgroundColor: theme.colors.successSurface },
        wrong: { borderColor: theme.colors.danger, backgroundColor: theme.colors.dangerSurface },
      },
    },
  },
  letter: {
    width: 32,
    height: 32,
    borderRadius: theme.m.radius.pill,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: theme.colors.toneNeutralBg,
    variants: {
      state: {
        selected: { backgroundColor: theme.colors.toneInfoBg },
        right: { backgroundColor: theme.colors.toneSuccessBg },
        wrong: { backgroundColor: theme.colors.toneDangerBg },
      },
    },
  },
  letterText: { color: theme.colors.text },
  text: { flex: 1, gap: 2 },
  pressed: { opacity: 0.85 },
}));
