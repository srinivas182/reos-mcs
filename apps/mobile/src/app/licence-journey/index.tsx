import { router } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { CheckCircle2, Circle, ClipboardCheck, ExternalLink, Luggage, RotateCcw } from 'lucide-react-native';
import { useState } from 'react';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { ShareNews, OffersOptIn } from '@/features/licence/ShareNews';
import { checkStep, dayText, type Journey, optInOffers, reloadJourney, setExam, shareNews, skipNews, useJourney } from '@/lib/licence';
import { asTopic, startPlan } from '@/lib/practice';
import { Button, Callout, Card, Icon, Screen, SectionTitle, Text, TextField, TitleBar } from '@/ui';

function ExamForm({ journey, onSave }: { journey: Journey; onSave: (on: string | null, time: string | null, centre: string | null) => void }) {
  const { t } = useTranslation();
  const [date, setDate] = useState(journey.exam.on ?? '');
  const [time, setTime] = useState(journey.exam.time ?? '');
  const [centre, setCentre] = useState(journey.exam.centre ?? '');

  return (
    <View style={styles.form}>
      <TextField label={t('licence.book.date')} hint="YYYY-MM-DD" value={date} onChangeText={setDate} maxLength={10} keyboardType="numbers-and-punctuation" />
      <TextField label={t('licence.book.time')} hint="HH:MM" value={time} onChangeText={setTime} maxLength={5} keyboardType="numbers-and-punctuation" />
      <TextField label={t('licence.book.centre')} value={centre} onChangeText={setCentre} maxLength={160} />
      <Text variant="caption" tone="muted">
        {t('licence.book.dateHint')}
      </Text>
      <Button label={t('licence.book.save')} disabled={date.trim() === ''} onPress={() => onSave(date.trim(), time.trim() || null, centre.trim() || null)} />
    </View>
  );
}

/**
 * Licence journey on the phone (candidate screen 16, with Ready to book,
 * 15, and the retake plan, 19; A10.3a): the window, the exam date, the
 * state's steps with check-offs, and what to do after the exam.
 */
export default function LicenceJourneyScreen() {
  const { t, i18n } = useTranslation();
  const { journey, problem, run } = useJourney();

  if (!journey) {
    return <Screen>{null}</Screen>;
  }

  const { window: win, readiness, exam } = journey;
  const practise = async (topic: string) => {
    const known = asTopic(topic);
    const plan = known ? await startPlan(known).catch(() => null) : null;
    if (plan) router.push({ pathname: '/practice/fix/[id]', params: { id: String(plan.id) } });
  };

  return (
    <Screen>
      <TitleBar title={t('licence.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('licence.purpose', { state: journey.state.name })}</Text>
      {problem ? <Callout tone="danger" title={problem} live /> : null}

      <Card>
        <Text weight="semibold">{t('licence.window.title')}</Text>
        {win.filed_on && win.ends_on && win.days_left !== null ? (
          <>
            <Text>{t('licence.window.left', { count: Math.max(0, win.days_left), date: dayText(win.ends_on, i18n.language) })}</Text>
            <Text variant="caption" tone="muted">
              {t('licence.window.filed', { date: dayText(win.filed_on, i18n.language), months: win.months })}
            </Text>
          </>
        ) : (
          <Text tone="muted">{t('licence.window.notFiled', { months: win.months })}</Text>
        )}
      </Card>

      {journey.results.can_enter ? (
        <Callout tone="info" title={t('licence.result.prompt')} action={<Button icon={ClipboardCheck} label={t('licence.result.title')} onPress={() => router.push('/licence-journey/result')} />} />
      ) : null}

      {journey.retake ? (
        <Card>
          <View style={styles.row}>
            <Icon icon={RotateCcw} color="action" />
            <Text weight="semibold">{t('licence.retake.title')}</Text>
          </View>
          <Text>{t('licence.retake.body', { portions: journey.retake.portions.map((p) => t(`licence.portions.${p}`)).join(', ') })}</Text>
          {journey.retake.more_education ? (
            <Callout tone="danger" title={t('licence.retake.moreEducation')} />
          ) : (
            <Text tone="muted">
              {journey.retake.portions.map((p) => t('licence.retake.attemptsLeft', { portion: t(`licence.portions.${p}`), count: journey.retake?.attempts_left[p] ?? 0 })).join(' ')}
            </Text>
          )}
          {journey.retake.topics.map((topic) => {
            const known = asTopic(topic);

            return (
              <View key={topic} style={styles.row}>
                <Text style={styles.flex}>{known ? t(`practice.topics.${known}`) : topic}</Text>
                <Button label={t('licence.retake.start')} onPress={() => void practise(topic)} />
              </View>
            );
          })}
        </Card>
      ) : null}

      {/* After passing: share the news (f05, A10.3b). */}
      {journey.news === 'prompt' ? (
        <ShareNews
          state={journey.state.name}
          city={journey.offers.city}
          onShare={(choice) => void run(async () => (await shareNews(choice), await reloadJourney()))}
          onSkip={() => void run(async () => (await skipNews(), await reloadJourney()))}
        />
      ) : null}

      {journey.results.passed_both ? (
        <Callout tone="success" title={t('licence.passed.title')} body={t('licence.passed.body')} />
      ) : (
        <Card>
          <Text weight="semibold">{readiness.ready ? t('licence.book.ready') : t('licence.book.notYet')}</Text>
          <Text>
            {`${t('licence.portions.national')}: ${readiness.national === null ? t('licence.book.unknown') : `${readiness.national}%`} · ${t('licence.portions.state')}: ${
              readiness.state === null ? t('licence.book.unknown') : `${readiness.state}%`
            } · ${t('licence.book.simulations')}: ${t('licence.book.simulationsValue', { count: readiness.simulations, needed: readiness.needed })}`}
          </Text>
          <Text tone="muted">
            {readiness.ready ? t('licence.book.readyBody', { vendor: exam.vendor ?? '' }) : t('licence.book.notYetBody', { line: readiness.line, needed: readiness.needed })}
          </Text>
          {readiness.ready && exam.book_url ? (
            <Button icon={ExternalLink} label={t('licence.book.button', { vendor: exam.vendor ?? '' })} onPress={() => void WebBrowser.openBrowserAsync(exam.book_url ?? '')} />
          ) : null}
          <Text weight="semibold">
            {exam.on ? t('licence.book.booked', { date: dayText(exam.on, i18n.language), count: exam.days_to ?? 0 }) : t('licence.book.dateTitle')}
          </Text>
          <ExamForm key={exam.on ?? 'none'} journey={journey} onSave={(on, time, centre) => void run(() => setExam(on, time, centre))} />
          {exam.on ? <Button variant="secondary" icon={Luggage} label={t('licence.kit.title')} onPress={() => router.push('/licence-journey/exam-day')} /> : null}
        </Card>
      )}

      {/* Broker offers (screen 17) and passes (f06), A10.3b. */}
      <OffersOptIn
        key={`${String(journey.offers.opted_in)}-${journey.offers.city ?? ''}`}
        state={journey.state.name}
        offers={journey.offers}
        onSave={(on, city) => void run(async () => (await optInOffers(on, city), await reloadJourney()))}
        onSee={() => router.push('/licence-journey/offers')}
      />
      <Button variant="secondary" label={t('licence.news.passes.see')} onPress={() => router.push('/licence-journey/passes')} />
      {/* Licensed: enter the number and switch to agent (A10.3c, f03). */}
      <Button variant={journey.results.passed_both ? 'primary' : 'secondary'} label={t('licence.switch.cta')} onPress={() => router.push('/licence-journey/licence')} />

      <SectionTitle>{t('licence.steps', { state: journey.state.name })}</SectionTitle>
      {journey.steps.map((step, i) => {
        const done = step.done_on !== null;

        return (
          <Card key={step.code}>
            <View style={styles.row}>
              <Icon icon={done ? CheckCircle2 : Circle} color={done ? 'success' : 'textMuted'} />
              <Text weight="semibold" style={styles.flex}>
                {t('licence.stepNumber', { number: i + 1, title: step.title })}
              </Text>
            </View>
            <Text tone="muted">{step.detail}</Text>
            {done && step.done_on ? (
              <Text tone="success">{step.auto ? t('licence.doneAuto', { date: dayText(step.done_on, i18n.language) }) : t('licence.doneOn', { date: dayText(step.done_on, i18n.language) })}</Text>
            ) : null}
            <View style={styles.actions}>
              {step.url ? <Button variant="ghost" icon={ExternalLink} label={t('licence.whatToDo')} onPress={() => void WebBrowser.openBrowserAsync(step.url ?? '')} /> : null}
              {step.auto ? null : (
                <Button variant={done ? 'secondary' : 'primary'} label={done ? t('licence.undo', { title: step.title }) : t('licence.markDone')} onPress={() => void run(() => checkStep(step.code, !done))} />
              )}
            </View>
          </Card>
        );
      })}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  form: { gap: theme.space.sm },
  row: { flexDirection: 'row', alignItems: 'center', gap: theme.space.sm, flexWrap: 'wrap' },
  actions: { flexDirection: 'row', gap: theme.space.sm, flexWrap: 'wrap' },
  flex: { flex: 1 },
}));
