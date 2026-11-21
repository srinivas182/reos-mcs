import { Pressable, View } from 'react-native';
import { StyleSheet } from 'react-native-unistyles';

import { Text } from './Text';

type Props<T extends string> = {
  /** Read by screen readers: "Text size". */
  label: string;
  value: T;
  /** Smallest first. */
  steps: readonly { value: T; label: string }[];
  onChange: (value: T) => void;
};

/**
 * The journey's text-size slider (screen 192): a small "A", a track with a
 * stop per size, a large "A". Tap a stop, or swipe up and down with a
 * screen reader.
 */
export function TextSizeControl<T extends string>({ label, value, steps, onChange }: Props<T>) {
  const index = Math.max(0, steps.findIndex((step) => step.value === value));
  const move = (to: number) => {
    const next = steps[Math.min(steps.length - 1, Math.max(0, to))];
    if (next && next.value !== value) {
      onChange(next.value);
    }
  };

  return (
    <View
      accessible
      accessibilityRole="adjustable"
      accessibilityLabel={label}
      accessibilityValue={{ text: steps[index]?.label }}
      accessibilityActions={[{ name: 'increment' }, { name: 'decrement' }]}
      onAccessibilityAction={(event) => move(event.nativeEvent.actionName === 'increment' ? index + 1 : index - 1)}
      style={styles.card}
    >
      <Text importantForAccessibility="no">A</Text>
      <View style={styles.track}>
        <View style={styles.rail} />
        <View style={styles.fill(index / Math.max(1, steps.length - 1))} />
        {steps.map((step, i) => (
          <Pressable
            key={step.value}
            importantForAccessibility="no"
            accessibilityElementsHidden
            onPress={() => move(i)}
            style={styles.stop(i / Math.max(1, steps.length - 1))}
            hitSlop={8}
          >
            <View style={i === index ? styles.thumb : styles.dot} />
          </Pressable>
        ))}
      </View>
      <Text variant="title" importantForAccessibility="no">
        A
      </Text>
    </View>
  );
}

const styles = StyleSheet.create((theme) => ({
  card: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.space.lg,
    backgroundColor: theme.colors.surface,
    borderRadius: theme.m.radius.card,
    borderWidth: 1,
    borderColor: theme.colors.border,
    paddingHorizontal: theme.space.lg,
    minHeight: theme.touch + theme.space.lg,
  },
  track: { flex: 1, height: theme.touch, justifyContent: 'center' },
  rail: { height: theme.space.xs, borderRadius: theme.m.radius.pill, backgroundColor: theme.colors.border },
  fill: (fraction: number) => ({
    position: 'absolute',
    left: 0,
    width: `${fraction * 100}%`,
    height: theme.space.xs,
    borderRadius: theme.m.radius.pill,
    backgroundColor: theme.colors.action,
  }),
  stop: (fraction: number) => ({
    position: 'absolute',
    left: `${fraction * 100}%`,
    width: theme.touch,
    height: theme.touch,
    marginLeft: -theme.touch / 2,
    alignItems: 'center',
    justifyContent: 'center',
  }),
  dot: { width: theme.space.md, height: theme.space.md, borderRadius: theme.m.radius.pill, backgroundColor: theme.colors.border },
  thumb: {
    width: theme.space.xl,
    height: theme.space.xl,
    borderRadius: theme.m.radius.pill,
    backgroundColor: theme.colors.surface,
    borderWidth: 3,
    borderColor: theme.colors.action,
  },
}));
