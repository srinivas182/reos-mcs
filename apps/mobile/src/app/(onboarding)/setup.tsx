import { supportedLocales, localeNames, type Locale } from '@reos/i18n';
import { router } from 'expo-router';
import { Clock } from 'lucide-react-native';
import { useMemo, useState } from 'react';
import { Pressable, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { api } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import { cancelDailyReminder, scheduleDailyReminder } from '@/lib/reminders';
import { Button, Callout, Chip, Icon, PillGroup, Screen, setPreferences, Sheet, Text, usePreferences } from '@/ui';

const REMINDER_TIMES = ['07:30', '12:30', '19:30', '21:00'] as const;

/** The next four months as YYYY-MM, starting next month. */
function nextMonths(count: number): string[] {
  const now = new Date();

  return Array.from({ length: count }, (_, i) => {
    const date = new Date(now.getFullYear(), now.getMonth() + 1 + i, 1);

    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
  });
}

/**
 * 60-second setup (mobile journey screen 8): exam month, minutes a day,
 * language and a daily reminder. No forms. Notification permission is asked
 * only once a reminder time is picked.
 */
export default function SetupScreen() {
  const { t, i18n } = useTranslation();
  const preferences = usePreferences();
  const { refresh } = useAuth();
  const months = useMemo(() => nextMonths(4), []);
  const [exam, setExam] = useState<string>(months[1] ?? 'not_sure');
  const [minutes, setMinutes] = useState('20');
  const [reminder, setReminder] = useState<string | null>('19:30');
  const [picking, setPicking] = useState(false);
  const [busy, setBusy] = useState(false);
  const [notice, setNotice] = useState<string | null>(null);
  const language = (preferences.locale === 'device' ? i18n.language : preferences.locale) as Locale;

  const monthName = (value: string) => new Intl.DateTimeFormat(i18n.language, { month: 'short' }).format(new Date(`${value}-15T12:00:00`));
  const timeName = (value: string) => new Intl.DateTimeFormat(i18n.language, { hour: 'numeric', minute: '2-digit' }).format(new Date(`2026-01-01T${value}:00`));

  const build = async () => {
    setBusy(true);
    const { response } = await api().PUT('/me/onboarding', {
      body: {
        exam_month: exam === 'not_sure' ? null : exam,
        minutes_per_day: Number(minutes),
        locale: language,
        reminder_time: reminder,
        complete: true,
      },
    });

    if (reminder) {
      const scheduled = await scheduleDailyReminder(
        reminder,
        t('onboarding.setup.reminderTitle'),
        t('onboarding.setup.reminderBody', { minutes }),
      ).catch(() => false);
      if (!scheduled) {
        setNotice(t('onboarding.setup.notificationsOff'));
      }
    } else {
      await cancelDailyReminder();
    }

    await refresh();
    setBusy(false);

    if (response.ok) {
      router.replace('/plan');
    }
  };

  return (
    <Screen footer={<View style={styles.footer}><Button label={t('onboarding.setup.build')} loading={busy} fullWidth onPress={() => void build()} /></View>}>
      <View style={styles.top}>
        <Text tone="muted" weight="semibold" style={styles.flex}>
          {t('onboarding.setup.header')}
        </Text>
        <Chip label={t('onboarding.setup.time')} />
      </View>

      <Text variant="lead" heading>
        {t('onboarding.setup.examTitle')}
      </Text>
      <PillGroup
        label={t('onboarding.setup.examTitle')}
        value={exam}
        options={[...months.map((value) => ({ value, label: monthName(value) })), { value: 'not_sure', label: t('onboarding.setup.notSure') }]}
        onChange={setExam}
      />

      <Text variant="lead" heading>
        {t('onboarding.setup.minutesTitle')}
      </Text>
      <PillGroup
        label={t('onboarding.setup.minutesTitle')}
        value={minutes}
        options={[
          ...['10', '20', '30', '45'].map((value) => ({ value, label: t('onboarding.setup.minutes', { count: Number(value) }) })),
          { value: '60', label: t('onboarding.setup.hourPlus') },
        ]}
        onChange={setMinutes}
      />

      <Text variant="lead" heading>
        {t('onboarding.setup.languageTitle')}
      </Text>
      <PillGroup
        label={t('onboarding.setup.languageTitle')}
        value={language}
        options={supportedLocales.map((value) => ({ value, label: localeNames[value] }))}
        onChange={(locale) => setPreferences({ locale })}
      />

      <Text variant="lead" heading>
        {t('onboarding.setup.remindTitle')}
      </Text>
      <Pressable
        accessibilityRole="button"
        accessibilityLabel={`${t('onboarding.setup.remindTitle')}: ${reminder ? `${t('onboarding.setup.remindEvery')} ${timeName(reminder)}` : t('onboarding.setup.remindNone')}`}
        onPress={() => setPicking(true)}
        style={({ pressed }) => [styles.reminder, pressed && styles.pressed]}
      >
        <Icon icon={Clock} color="textMuted" />
        <Text style={styles.flex}>{reminder ? t('onboarding.setup.remindEvery') : t('onboarding.setup.remindNone')}</Text>
        {reminder ? <Chip label={timeName(reminder)} tone="info" /> : null}
      </Pressable>
      {notice ? <Callout tone="warning" title={notice} live /> : null}

      <Sheet open={picking} onClose={() => setPicking(false)} title={t('onboarding.setup.remindChoose')}>
        <PillGroup
          label={t('onboarding.setup.remindChoose')}
          value={reminder ?? 'none'}
          options={[...REMINDER_TIMES.map((value) => ({ value, label: timeName(value) })), { value: 'none', label: t('onboarding.setup.remindNone') }]}
          onChange={(value) => {
            setReminder(value === 'none' ? null : value);
            setPicking(false);
          }}
        />
      </Sheet>
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  footer: { paddingHorizontal: theme.m.gutter, paddingBottom: theme.space.xl },
  top: { flexDirection: 'row', alignItems: 'center', gap: theme.space.md },
  flex: { flex: 1 },
  reminder: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.space.md,
    minHeight: theme.touch + theme.space.md,
    paddingHorizontal: theme.space.lg,
    borderRadius: theme.m.radius.card,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: theme.colors.surface,
  },
  pressed: { opacity: 0.85 },
}));
