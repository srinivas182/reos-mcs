import { router } from 'expo-router';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { useAuth } from '@/lib/auth';
import { Button, Card, Eyebrow, HeroCard, ProgressRing, Screen, Text } from '@/ui';

/**
 * Plan built (mobile journey screen 9): days to the target exam and the
 * four-step road to a license. The plan recalculates as courses arrive
 * (A5); for now it shows the road and the commitment made in setup.
 */
export default function PlanScreen() {
  const { t, i18n } = useTranslation();
  const { user } = useAuth();
  const onboarding = user?.onboarding;
  const minutes = onboarding?.minutes_per_day ?? 20;
  const hours = onboarding?.plan.qualifying_hours ?? null;
  const days = onboarding?.plan.days_to_exam ?? null;
  const month = onboarding?.exam_month
    ? new Intl.DateTimeFormat(i18n.language, { month: 'long', year: 'numeric' }).format(new Date(`${onboarding.exam_month}-15T12:00:00`))
    : null;

  const steps = [
    { title: t('onboarding.plan.steps.courses'), detail: hours ? t('onboarding.plan.steps.coursesDetail', { hours }) : t('onboarding.plan.steps.coursesDetailNoHours') },
    { title: t('onboarding.plan.steps.prep'), detail: t('onboarding.plan.steps.prepDetail') },
    { title: t('onboarding.plan.steps.exam'), detail: t('onboarding.plan.steps.examDetail') },
    { title: t('onboarding.plan.steps.license'), detail: t('onboarding.plan.steps.licenseDetail') },
  ];

  return (
    <Screen footer={<View style={styles.footer}><Button label={t('onboarding.plan.start')} fullWidth onPress={() => router.replace('/')} /></View>}>
      <Eyebrow>{t('onboarding.plan.eyebrow')}</Eyebrow>
      <Text variant="headline" heading>
        {month ? t('onboarding.plan.titleMonth', { month }) : t('onboarding.plan.titleNotSure')}
      </Text>

      <HeroCard style={styles.hero}>
        {days !== null ? (
          <ProgressRing value={100} label={`${days} ${t('onboarding.plan.days')}`} size={96}>
            <Text variant="title" style={styles.light}>
              {String(days)}
            </Text>
            <Text variant="caption" style={styles.muted}>
              {t('onboarding.plan.days')}
            </Text>
          </ProgressRing>
        ) : null}
        <View style={styles.heroText}>
          {month ? (
            <>
              <Text variant="caption" style={styles.muted}>
                {t('onboarding.plan.targetExam')}
              </Text>
              <Text variant="title" style={styles.light}>
                {month}
              </Text>
            </>
          ) : null}
          <Text style={styles.muted}>{hours ? t('onboarding.plan.perDay', { minutes, hours }) : t('onboarding.plan.perDayNoHours', { minutes })}</Text>
        </View>
      </HeroCard>

      <Card>
        {steps.map((step, index) => (
          <View key={step.title} accessible accessibilityLabel={`${index + 1}. ${step.title}. ${step.detail}`} style={styles.step}>
            <View style={styles.number}>
              <Text weight="bold" tone="action">
                {String(index + 1)}
              </Text>
            </View>
            <View style={styles.flex}>
              <Text weight="semibold">{step.title}</Text>
              <Text variant="caption" tone="muted">
                {step.detail}
              </Text>
            </View>
          </View>
        ))}
      </Card>
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  footer: { paddingHorizontal: theme.m.gutter, paddingBottom: theme.space.xl },
  hero: { flexDirection: 'row', alignItems: 'center', gap: theme.space.lg },
  heroText: { flex: 1, gap: theme.space.xxs },
  light: { color: theme.colors.heroText },
  muted: { color: theme.colors.heroMuted },
  step: { flexDirection: 'row', alignItems: 'center', gap: theme.space.md, minHeight: theme.touch + theme.space.sm },
  number: {
    width: theme.space.xxl,
    height: theme.space.xxl,
    borderRadius: theme.m.radius.tile,
    backgroundColor: theme.colors.actionTint,
    alignItems: 'center',
    justifyContent: 'center',
  },
  flex: { flex: 1 },
}));
