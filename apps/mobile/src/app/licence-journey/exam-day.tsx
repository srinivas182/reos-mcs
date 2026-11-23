import * as Speech from 'expo-speech';
import { router } from 'expo-router';
import { MapPin, Pause, Play } from 'lucide-react-native';
import { useEffect, useRef, useState } from 'react';
import { Linking } from 'react-native';
import { useTranslation } from 'react-i18next';

import { dayText, tickKit, useJourney } from '@/lib/licence';
import { Button, Callout, Card, Screen, SwitchRow, Text, TitleBar } from '@/ui';

const PHASES = [
  { key: 'in', seconds: 4 },
  { key: 'hold', seconds: 4 },
  { key: 'out', seconds: 6 },
] as const;
const TOTAL = 120;

/** Two minutes of paced breathing, spoken by the phone's own voice. */
function CalmDown() {
  const { t, i18n } = useTranslation();
  const [left, setLeft] = useState<number | null>(null);
  const timer = useRef<ReturnType<typeof setInterval> | undefined>(undefined);
  const cycle = PHASES.reduce((s, p) => s + p.seconds, 0);
  const at = (left === null ? 0 : TOTAL - left) % cycle;
  const phase = at < PHASES[0].seconds ? PHASES[0] : at < PHASES[0].seconds + PHASES[1].seconds ? PHASES[1] : PHASES[2];
  const label = t(`licence.calm.${phase.key}`);
  const running = left !== null;

  useEffect(() => () => clearInterval(timer.current), []);
  useEffect(() => {
    if (!running) return;
    void Speech.stop();
    Speech.speak(label, { language: i18n.language === 'es' ? 'es-US' : 'en-US', rate: 0.85 });
  }, [label, running, i18n.language]);

  const start = () => {
    setLeft(TOTAL);
    clearInterval(timer.current);
    timer.current = setInterval(
      () =>
        setLeft((s) => {
          if (s === null || s <= 1) {
            clearInterval(timer.current);

            return null;
          }

          return s - 1;
        }),
      1000,
    );
  };
  const stop = () => {
    clearInterval(timer.current);
    setLeft(null);
    void Speech.stop();
  };

  return (
    <Card>
      <Text weight="semibold">{t('licence.calm.title')}</Text>
      <Text tone="muted">{t('licence.calm.body')}</Text>
      {left !== null ? (
        <Text variant="title" weight="bold" accessibilityLiveRegion="polite">
          {`${label} · ${t('licence.calm.left', { time: `${Math.floor(left / 60)}:${String(left % 60).padStart(2, '0')}` })}`}
        </Text>
      ) : null}
      <Button variant={running ? 'secondary' : 'primary'} icon={running ? Pause : Play} label={running ? t('licence.calm.stop') : t('licence.calm.start')} onPress={running ? stop : start} />
    </Card>
  );
}

/**
 * Exam-day kit (candidate screen 14, A10.3a): when and where, Directions,
 * the checklist, the testing vendor's rules, readiness and a two-minute
 * calm-down.
 */
export default function ExamDayScreen() {
  const { t, i18n } = useTranslation();
  const { journey, problem, run } = useJourney();

  if (!journey) {
    return <Screen>{null}</Screen>;
  }

  const { exam } = journey;

  return (
    <Screen>
      <TitleBar title={t('licence.kit.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('licence.kit.purpose')}</Text>
      {problem ? <Callout tone="danger" title={problem} live /> : null}
      <Card>
        <Text weight="semibold">{exam.on ? [dayText(exam.on, i18n.language), exam.time].filter(Boolean).join(' · ') : t('licence.kit.noDate')}</Text>
        {exam.centre ? <Text>{exam.centre}</Text> : null}
        <Text tone="muted">{t('licence.kit.arrive')}</Text>
        {exam.directions_url ? <Button variant="secondary" icon={MapPin} label={t('licence.kit.directions')} onPress={() => void Linking.openURL(exam.directions_url ?? '')} /> : null}
      </Card>
      <Card>
        <Text weight="semibold">{t('licence.kit.checklist')}</Text>
        {journey.kit.items.map((item) => (
          <SwitchRow key={item.key} label={t(`licence.kit.items.${item.key}`)} value={item.done} onValueChange={(on) => void run(() => tickKit(item.key, on))} />
        ))}
      </Card>
      {journey.kit.rules ? (
        <Card>
          <Text weight="semibold">{t('licence.kit.rules', { vendor: exam.vendor ?? '' })}</Text>
          <Text>{journey.kit.rules}</Text>
        </Card>
      ) : null}
      <Text tone="muted">{t('licence.kit.readiness', { national: journey.readiness.national ?? 0, state: journey.readiness.state ?? 0 })}</Text>
      <CalmDown />
    </Screen>
  );
}
