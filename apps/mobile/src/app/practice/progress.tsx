import { router } from 'expo-router';
import { ChevronLeft, ChevronRight, Link2, School } from 'lucide-react-native';
import { useEffect, useState } from 'react';
import { ActivityIndicator, Share, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';

import { asTopic, loadWeeklyProgress, type ReportShares, shareWeek, type WeeklyProgress } from '@/lib/practice';
import { Button, Callout, Card, Screen, SectionTitle, Text, TitleBar } from '@/ui';

type Report = WeeklyProgress['report'];

/** "2026-11-16" moved by a number of days. */
function shift(day: string, days: number): string {
  const d = new Date(`${day}T12:00:00Z`);
  d.setUTCDate(d.getUTCDate() + days);

  return d.toISOString().slice(0, 10);
}

/**
 * Weekly progress (candidate screen 55, A9.2e): time studied against the
 * plan with minutes each day, questions, flashcards, the readiness change,
 * lessons, course hours, the weakest topic and the streak. Shared with the
 * school only when the candidate chooses, or as a first-name link for a
 * study buddy through the phone's share sheet.
 */
export default function ProgressScreen() {
  const { t } = useTranslation();
  const { theme } = useUnistyles();
  const [week, setWeek] = useState<string | undefined>(undefined);
  const [thisWeek, setThisWeek] = useState<string | null>(null);
  const [data, setData] = useState<WeeklyProgress | null | undefined>(undefined);
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    let active = true;
    void loadWeeklyProgress(week)
      .catch(() => null)
      .then((next) => {
        if (!active) return;
        setData(next);
        if (next && week === undefined) setThisWeek(next.report.week_start);
      });

    return () => {
      active = false;
    };
  }, [week]);

  const share = async (withWho: 'school' | 'link') => {
    if (!data) return;
    const shares = await shareWeek(withWho, data.report.week_start).catch(() => null);
    setFailed(shares === null);
    if (!shares) return;
    setData({ ...data, shares });
    if (withWho === 'link' && shares.link) void Share.share({ message: shares.link });
  };

  return (
    <Screen>
      <TitleBar title={t('practice.reports.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('practice.reports.description')}</Text>
      {data === undefined ? (
        <ActivityIndicator color={theme.colors.action} />
      ) : data === null ? (
        <Callout tone="warning" title={t('common.tryAgain')} live />
      ) : (
        <>
          <Week report={data.report} />
          <View style={styles.row}>
            <Button variant="secondary" icon={ChevronLeft} label={t('practice.reports.previous')} onPress={() => setWeek(shift(data.report.week_start, -7))} />
            {thisWeek !== null && data.report.week_start < thisWeek ? (
              <Button variant="secondary" icon={ChevronRight} label={t('practice.reports.next')} onPress={() => setWeek(shift(data.report.week_start, 7))} />
            ) : null}
          </View>
          <Sharing shares={data.shares} onShare={(w) => void share(w)} />
          {failed ? <Callout tone="danger" title={t('common.tryAgain')} live /> : null}
        </>
      )}
    </Screen>
  );
}

function Week({ report }: { report: Report }) {
  const { t, i18n } = useTranslation();
  const day = (d: string, options: Intl.DateTimeFormatOptions) => new Date(`${d}T12:00:00Z`).toLocaleDateString(i18n.language, { timeZone: 'UTC', ...options });
  const perDayGoal = report.goal_minutes / 7;
  const top = Math.max(perDayGoal, ...report.per_day, 1);
  const topic = report.weakest ? asTopic(report.weakest) : null;
  const change = report.readiness.change;
  const tiles: [string, string][] = [
    [t('practice.reports.minutes'), t('practice.reports.minutesValue', { count: report.minutes })],
    [t('practice.reports.questions'), String(report.questions)],
    [t('practice.reports.cards'), String(report.cards)],
    [
      t('practice.reports.readiness'),
      report.readiness.now === null
        ? t('practice.reports.notYet')
        : change === null
          ? t('practice.reports.readinessNow', { percent: report.readiness.now })
          : t('practice.reports.readinessChange', { percent: report.readiness.now, change: change > 0 ? `+${change}` : String(change) }),
    ],
    [t('practice.reports.lessons'), String(report.lessons)],
    [t('practice.reports.hours'), t('practice.reports.hoursValue', { hours: report.hours })],
    [t('practice.reports.weakest'), topic ? t(`practice.topics.${topic}`) : t('practice.reports.none')],
    [t('practice.reports.streak'), t('practice.reports.streakValue', { count: report.streak })],
  ];

  return (
    <>
      <Text tone="muted">{t('practice.reports.week', { start: day(report.week_start, { month: 'long', day: 'numeric' }), end: day(report.week_end, { month: 'long', day: 'numeric' }) })}</Text>
      <View style={styles.tiles}>
        {tiles.map(([label, value]) => (
          <Card key={label} style={styles.tile}>
            <Text variant="caption" tone="muted">
              {label}
            </Text>
            <Text weight="bold">{value}</Text>
          </Card>
        ))}
      </View>
      <SectionTitle>{t('practice.reports.perDay')}</SectionTitle>
      <Text variant="caption" tone="muted">
        {t('practice.reports.goal', { minutes: report.goal_minutes })}
      </Text>
      <View style={styles.bars}>
        {report.per_day.map((minutes, i) => {
          const name = day(shift(report.week_start, i), { weekday: 'short' });

          return (
            <View key={name} style={styles.bar} accessible accessibilityLabel={t('practice.reports.day', { day: name, minutes })}>
              <View style={[styles.fill(minutes >= perDayGoal), { height: `${Math.max(2, (minutes / top) * 100)}%` }]} />
              <Text variant="caption" tone="muted">
                {name}
              </Text>
            </View>
          );
        })}
      </View>
    </>
  );
}

function Sharing({ shares, onShare }: { shares: ReportShares; onShare: (withWho: 'school' | 'link') => void }) {
  const { t } = useTranslation();

  return (
    <Card>
      <Text weight="bold">{t('practice.reports.shareTitle')}</Text>
      <Text tone="muted">{t('practice.reports.shareBody')}</Text>
      {shares.can_share_school ? (
        shares.school ? (
          <Text>{t('practice.reports.sharedSchool')}</Text>
        ) : (
          <Button icon={School} label={t('practice.reports.shareSchool')} onPress={() => onShare('school')} />
        )
      ) : null}
      <Button variant="secondary" icon={Link2} label={shares.link ? t('practice.reports.copy') : t('practice.reports.shareLink')} onPress={() => onShare('link')} />
    </Card>
  );
}

const styles = StyleSheet.create((theme) => ({
  row: { flexDirection: 'row', flexWrap: 'wrap', gap: theme.space.xs },
  tiles: { flexDirection: 'row', flexWrap: 'wrap', gap: theme.space.sm },
  tile: { flexBasis: '47%', flexGrow: 1 },
  bars: { flexDirection: 'row', alignItems: 'flex-end', gap: theme.space.xs, height: 160 },
  bar: { flex: 1, height: '100%', alignItems: 'center', justifyContent: 'flex-end', gap: theme.space.xxs },
  fill: (met: boolean) => ({ width: '100%', borderTopLeftRadius: theme.radius.sm, borderTopRightRadius: theme.radius.sm, backgroundColor: met ? theme.colors.success : theme.colors.action }),
}));
