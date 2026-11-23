import { useState } from 'react';
import { Pressable, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { Sheet, Text } from '@/ui';

type Op = '+' | '−' | '×' | '÷';

export function apply(a: number, op: Op, b: number): number | null {
  if (op === '+') return a + b;
  if (op === '−') return a - b;
  if (op === '×') return a * b;

  return b === 0 ? null : a / b;
}

export function show(value: number): string {
  return Number.parseFloat(value.toPrecision(10)).toString();
}

/**
 * The on-screen calculator (candidate screen 41, A9.1): four operations,
 * percent (it completes the sum: "a × b %" is b% of a), memory and clear,
 * with 48-point keys.
 */
export function Calculator({ open, onClose }: { open: boolean; onClose: () => void }) {
  const { t } = useTranslation();
  const [display, setDisplay] = useState('0');
  const [stored, setStored] = useState<number | null>(null);
  const [op, setOp] = useState<Op | null>(null);
  const [fresh, setFresh] = useState(true);
  const [memory, setMemory] = useState(0);
  const value = Number.parseFloat(display);

  const digit = (d: string) => {
    if (d === '.' && display.includes('.') && !fresh) return;
    setDisplay(fresh ? (d === '.' ? '0.' : d) : display === '0' && d !== '.' ? d : display + d);
    setFresh(false);
  };

  const done = (result: number | null) => {
    setDisplay(result === null ? t('practice.calculator.error') : show(result));
    setStored(null);
    setOp(null);
    setFresh(true);
  };

  const operate = (next: Op | null) => {
    const result = op !== null && stored !== null && !fresh ? apply(stored, op, value) : value;
    if (result === null || next === null) {
      done(result);

      return;
    }
    setDisplay(show(result));
    setStored(result);
    setOp(next);
    setFresh(true);
  };

  const percent = () => {
    if (stored === null || op === null) return done(value / 100);
    if (op === '×') return done((stored * value) / 100);
    if (op === '÷') return done(value === 0 ? null : (stored / value) * 100);

    return done(op === '+' ? stored + (stored * value) / 100 : stored - (stored * value) / 100);
  };

  const keys: { label: string; a11y?: string; run: () => void }[] = [
    { label: 'MC', a11y: t('practice.calculator.mc'), run: () => setMemory(0) },
    { label: 'MR', a11y: t('practice.calculator.mr'), run: () => { setDisplay(show(memory)); setFresh(true); } },
    { label: 'M+', a11y: t('practice.calculator.mPlus'), run: () => setMemory(memory + value) },
    { label: 'M−', a11y: t('practice.calculator.mMinus'), run: () => setMemory(memory - value) },
    { label: 'C', a11y: t('practice.calculator.clear'), run: () => { setDisplay('0'); setStored(null); setOp(null); setFresh(true); } },
    { label: '%', a11y: t('practice.calculator.percent'), run: percent },
    { label: '÷', run: () => operate('÷') },
    { label: '×', run: () => operate('×') },
    ...['7', '8', '9'].map((d) => ({ label: d, run: () => digit(d) })),
    { label: '−', run: () => operate('−') },
    ...['4', '5', '6'].map((d) => ({ label: d, run: () => digit(d) })),
    { label: '+', run: () => operate('+') },
    ...['1', '2', '3'].map((d) => ({ label: d, run: () => digit(d) })),
    { label: '=', a11y: t('practice.calculator.equals'), run: () => operate(null) },
    { label: '0', run: () => digit('0') },
    { label: '.', a11y: t('practice.calculator.point'), run: () => digit('.') },
  ];

  return (
    <Sheet open={open} onClose={onClose} title={t('practice.calculator.title')}>
      <View style={styles.display} accessibilityLiveRegion="polite">
        <Text variant="title" weight="bold" style={styles.right}>
          {display}
        </Text>
      </View>
      <View style={styles.grid}>
        {keys.map((key) => (
          <Pressable key={key.label} accessibilityRole="button" accessibilityLabel={key.a11y ?? key.label} onPress={key.run} style={({ pressed }) => [styles.key, pressed && styles.pressed]}>
            <Text variant="lead" weight="semibold">
              {key.label}
            </Text>
          </Pressable>
        ))}
      </View>
    </Sheet>
  );
}

const styles = StyleSheet.create((theme) => ({
  display: { borderRadius: theme.radius.md, backgroundColor: theme.colors.surfaceMuted, padding: theme.space.sm },
  right: { textAlign: 'right' },
  grid: { flexDirection: 'row', flexWrap: 'wrap', gap: theme.space.xs },
  key: { width: '23%', minHeight: 56, alignItems: 'center', justifyContent: 'center', borderRadius: theme.radius.md, borderWidth: 1, borderColor: theme.colors.border },
  pressed: { backgroundColor: theme.colors.actionTint },
}));
