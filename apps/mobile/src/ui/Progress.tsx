import type { ReactNode } from 'react';
import { View } from 'react-native';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';
import Svg, { Circle } from 'react-native-svg';

import type { SemanticColors } from '@reos/tokens';

const clamp = (value: number): number => Math.max(0, Math.min(100, value));

type ProgressBarProps = {
  /** 0 to 100. */
  value: number;
  /** What's being measured, for screen readers: "Quiz progress". */
  label: string;
  color?: keyof SemanticColors;
};

/** A thin rounded bar (quiz progress, CE hours, course completion). */
export function ProgressBar({ value, label, color = 'action' }: ProgressBarProps) {
  const pct = clamp(value);

  return (
    <View
      accessible
      accessibilityRole="progressbar"
      accessibilityLabel={label}
      accessibilityValue={{ min: 0, max: 100, now: Math.round(pct) }}
      style={styles.track}
    >
      <View style={styles.fill(pct, color)} />
    </View>
  );
}

type ProgressRingProps = {
  value: number;
  label: string;
  size?: number;
  /** Centre content, usually the number and a word: "68%" / "ready". */
  children?: ReactNode;
  color?: keyof SemanticColors;
  track?: keyof SemanticColors;
};

/** A ring that fills clockwise from the top (exam readiness, CE progress). */
export function ProgressRing({ value, label, size = 128, children, color = 'heroProgress', track = 'heroTrack' }: ProgressRingProps) {
  const { theme } = useUnistyles();
  const pct = clamp(value);
  const stroke = 12;
  const r = (size - stroke) / 2;
  const circumference = 2 * Math.PI * r;

  return (
    <View
      accessible
      accessibilityRole="progressbar"
      accessibilityLabel={label}
      accessibilityValue={{ min: 0, max: 100, now: Math.round(pct) }}
      style={{ width: size, height: size }}
    >
      <View style={styles.svg}>
        <Svg width={size} height={size}>
        <Circle cx={size / 2} cy={size / 2} r={r} stroke={theme.colors[track]} strokeWidth={stroke} fill="none" />
        <Circle
          cx={size / 2}
          cy={size / 2}
          r={r}
          stroke={theme.colors[color]}
          strokeWidth={stroke}
          fill="none"
          strokeLinecap="round"
          strokeDasharray={`${circumference} ${circumference}`}
          strokeDashoffset={circumference * (1 - pct / 100)}
          transform={`rotate(-90 ${size / 2} ${size / 2})`}
        />
        </Svg>
      </View>
      <View style={styles.centre}>{children}</View>
    </View>
  );
}

const styles = StyleSheet.create((theme) => ({
  track: { height: 8, borderRadius: theme.m.radius.pill, backgroundColor: theme.colors.border, overflow: 'hidden' },
  fill: (pct: number, color: keyof SemanticColors) => ({
    width: `${pct}%`,
    height: '100%',
    borderRadius: theme.m.radius.pill,
    backgroundColor: theme.colors[color],
  }),
  svg: { position: 'absolute', top: 0, left: 0, right: 0, bottom: 0 },
  centre: { flex: 1, alignItems: 'center', justifyContent: 'center' },
}));
