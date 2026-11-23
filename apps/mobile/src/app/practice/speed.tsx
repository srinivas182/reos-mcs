import { router } from 'expo-router';
import { Check, X } from 'lucide-react-native';
import { useEffect, useRef, useState } from 'react';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { finishSpeedRound, type SpeedResult, type SpeedRound, startSpeedRound } from '@/lib/flashcards';
import { Button, Callout, Card, Screen, Text, TitleBar } from '@/ui';

/**
 * The speed round (candidate screen 50, A9.2d): 60 seconds of true or
 * false ("is this the answer?"), the countdown, the best and current
 * score, and two big buttons. The server scores the taps, keeps the best,
 * and saves the misses as flashcards.
 */
export default function SpeedScreen() {
  const { t } = useTranslation();
  const [round, setRound] = useState<SpeedRound | null>(null);
  const [at, setAt] = useState(0);
  const [left, setLeft] = useState(0);
  const [score, setScore] = useState(0);
  const [last, setLast] = useState<boolean | null>(null);
  const [result, setResult] = useState<SpeedResult | null>(null);
  const [failed, setFailed] = useState(false);
  const taps = useRef<{ id: number; true: boolean }[]>([]);
  const sent = useRef(false);

  const begin = async () => {
    const next = await startSpeedRound().catch(() => null);
    if (!next) return setFailed(true);
    taps.current = [];
    sent.current = false;
    setRound(next);
    setAt(0);
    setScore(0);
    setLast(null);
    setResult(null);
    setLeft(next.seconds);
    setFailed(false);
  };

  const end = async (current: SpeedRound) => {
    if (sent.current) return;
    sent.current = true;
    const done = await finishSpeedRound(current.id, taps.current).catch(() => null);
    if (done) setResult(done);
    else setFailed(true);
  };

  useEffect(() => {
    if (!round || result || left <= 0) return;
    const timer = setTimeout(() => {
      setLeft((s) => s - 1);
      if (left === 1) void end(round);
    }, 1000);

    return () => clearTimeout(timer);
  }, [round, result, left]);

  const tap = (answer: boolean) => {
    const statement = round?.statements[at];
    if (!round || !statement || left <= 0) return;
    taps.current.push({ id: statement.id, true: answer });
    const right = answer === statement.truth;
    setLast(right);
    if (right) setScore((s) => s + 1);
    if (at + 1 >= round.statements.length) void end(round);
    else setAt(at + 1);
  };

  const statement = round?.statements[at];
  const best = Math.max(round?.best ?? 0, result?.best ?? 0);

  return (
    <Screen>
      <TitleBar title={t('practice.speed.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('practice.speed.description')}</Text>
      <Text>{round?.best !== null && round?.best !== undefined ? t('practice.speed.best', { score: best }) : t('practice.speed.noBest')}</Text>
      {!round || result ? (
        <>
          {result ? (
            <Callout
              tone="success"
              title={t('practice.speed.doneTitle')}
              body={[t('practice.speed.doneBody', { score: result.score, answered: result.answered }), result.record && result.score > 0 ? t('practice.speed.record') : null, result.misses > 0 ? t('practice.speed.misses', { count: result.misses }) : null].filter(Boolean).join('\n')}
              live
            />
          ) : null}
          <Button label={result ? t('practice.speed.again') : t('practice.speed.start')} onPress={() => void begin()} fullWidth />
        </>
      ) : statement ? (
        <>
          <View style={styles.row}>
            <Text weight="bold" accessibilityLabel={t('practice.speed.timeLeft', { count: left })}>
              {String(left)}
            </Text>
            <Text weight="bold">{t('practice.speed.score', { score })}</Text>
          </View>
          <Card>
            <Text variant="lead" weight="semibold">
              {statement.prompt}
            </Text>
            <Text variant="bodyLarge">{t('practice.speed.proposed', { answer: statement.answer })}</Text>
          </Card>
          <View style={styles.row}>
            <View style={styles.flex}>
              <Button icon={Check} label={t('practice.speed.true')} onPress={() => tap(true)} fullWidth />
            </View>
            <View style={styles.flex}>
              <Button variant="secondary" icon={X} label={t('practice.speed.false')} onPress={() => tap(false)} fullWidth />
            </View>
          </View>
          {last !== null ? <Callout tone={last ? 'success' : 'warning'} title={last ? t('practice.speed.right') : t('practice.speed.wrong')} live /> : null}
        </>
      ) : null}
      {failed ? <Callout tone="danger" title={t('practice.errors.save')} live /> : null}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  row: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: theme.space.sm },
  flex: { flex: 1 },
}));
