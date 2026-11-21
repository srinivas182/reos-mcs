import { router } from 'expo-router';
import { ArrowRight } from 'lucide-react-native';
import { Pressable, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { useDraft } from '@/lib/onboarding';
import { BrandMark, Button, Screen, StatTile, Text } from '@/ui';

const stateNames: Record<string, string> = { TX: 'Texas' };

/**
 * Welcome (mobile journey screen 5): one promise and one big button, a free
 * quiz with no sign-up. A school, brokerage or association code is always
 * one tap away.
 */
export default function WelcomeScreen() {
  const { t } = useTranslation();
  const { state } = useDraft();
  const stateName = stateNames[state ?? 'TX'] ?? state ?? 'Texas';

  return (
    <Screen
      dark
      footer={
        <View style={styles.footer}>
          <Button label={t('onboarding.welcome.quiz')} trailingIcon={ArrowRight} fullWidth onPress={() => router.push('/quiz')} />
          <Button label={t('onboarding.welcome.haveAccount')} variant="heroOutline" fullWidth onPress={() => router.push('/sign-in')} />
          <Pressable accessibilityRole="button" onPress={() => router.push('/enter-code')} style={styles.link}>
            <Text weight="semibold" style={styles.linkText}>
              {t('onboarding.welcome.code')}
            </Text>
          </Pressable>
        </View>
      }
    >
      <BrandMark />
      <Text variant="caption" weight="bold" style={styles.eyebrow}>
        {t('onboarding.welcome.eyebrow', { state: stateName }).toUpperCase()}
      </Text>
      <Text variant="display" heading style={styles.light}>
        {t('onboarding.welcome.title')}
      </Text>
      <Text variant="bodyLarge" style={styles.muted}>
        {t('onboarding.welcome.body')}
      </Text>
      <View style={styles.stats}>
        <StatTile value={t('onboarding.welcome.dailyValue')} label={t('onboarding.welcome.daily')} />
        <StatTile value={t('onboarding.welcome.aiValue')} label={t('onboarding.welcome.ai')} />
        <StatTile value={t('onboarding.welcome.languagesValue')} label={t('onboarding.welcome.languages')} />
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  footer: { gap: theme.space.sm, paddingHorizontal: theme.m.gutter, paddingBottom: theme.space.lg, backgroundColor: theme.colors.heroBg },
  eyebrow: { color: theme.colors.heroAccent, letterSpacing: 1, marginTop: theme.space.xl },
  light: { color: theme.colors.heroText },
  muted: { color: theme.colors.heroMuted },
  stats: { flexDirection: 'row', gap: theme.space.sm, marginTop: theme.space.md },
  link: { alignSelf: 'center', minHeight: theme.touch, justifyContent: 'center', paddingHorizontal: theme.space.lg },
  linkText: { color: theme.colors.heroAccent, textAlign: 'center' },
}));
