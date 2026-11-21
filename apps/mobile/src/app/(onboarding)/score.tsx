import * as AppleAuthentication from 'expo-apple-authentication';
import { router } from 'expo-router';
import { Phone } from 'lucide-react-native';
import { useEffect, useMemo, useState } from 'react';
import { Platform, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';

import { useAuth, type Outcome, type SignInOptions } from '@/lib/auth';
import { appConfig } from '@/lib/monitoring';
import { topicLevels, useDraft } from '@/lib/onboarding';
import { Button, Callout, Card, Chip, ProgressRing, Screen, Text } from '@/ui';

const levelTone = { strong: 'success', learning: 'warning', start_here: 'danger' } as const;

/**
 * Score, then sign up (mobile journey screen 7): "you already know 40%",
 * three topics, then one-tap sign-up with Apple, Google or phone. The
 * answers are saved to the new account straight after.
 */
export default function ScoreScreen() {
  const { t } = useTranslation();
  const { theme } = useUnistyles();
  const { results = [] } = useDraft();
  const { signInWithApple, signInWithBrowser } = useAuth();
  const [options, setOptions] = useState<SignInOptions | null>(null);
  const [busy, setBusy] = useState<'apple' | 'google' | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let active = true;
    void appConfig().then((config) => active && setOptions(config?.sign_in ?? null));

    return () => {
      active = false;
    };
  }, []);

  const correct = results.filter((r) => r.correct).length;
  const percent = Math.round((100 * correct) / Math.max(1, results.length));
  // Up to three topics: the strongest, then the ones to work on.
  const topics = useMemo(() => {
    const order = { strong: 0, learning: 1, start_here: 2 };

    return topicLevels(results)
      .sort((a, b) => order[a.level] - order[b.level])
      .slice(0, 3);
  }, [results]);

  const run = async (provider: 'apple' | 'google', attempt: () => Promise<Outcome>) => {
    setBusy(provider);
    setError(null);
    const outcome = await attempt();
    setBusy(null);
    if (!outcome.ok && 'message' in outcome) {
      setError(outcome.message);
    }
  };

  const nativeApple = Platform.OS === 'ios' && options?.apple === true && !options.demo;

  return (
    <Screen
      footer={
        <View style={styles.footer}>
          <Text tone="muted" style={styles.center}>
            {t('mobile.onboarding.score.save')}
          </Text>
          {error ? <Callout tone="danger" title={error} live /> : null}
          {nativeApple ? (
            <AppleAuthentication.AppleAuthenticationButton
              buttonType={AppleAuthentication.AppleAuthenticationButtonType.CONTINUE}
              buttonStyle={theme.name === 'dark' ? AppleAuthentication.AppleAuthenticationButtonStyle.WHITE : AppleAuthentication.AppleAuthenticationButtonStyle.BLACK}
              cornerRadius={theme.m.radius.button}
              style={styles.apple}
              onPress={() => void run('apple', () => signInWithApple(false))}
            />
          ) : options?.apple ? (
            <Button label={t('mobile.signIn.apple')} loading={busy === 'apple'} fullWidth onPress={() => void run('apple', () => signInWithApple(options.demo))} />
          ) : null}
          {options?.google ? (
            <Button label={t('mobile.signIn.google')} variant="secondary" loading={busy === 'google'} fullWidth onPress={() => void run('google', () => signInWithBrowser('google'))} />
          ) : null}
          {options?.phone !== false ? <Button label={t('mobile.signIn.phone')} variant="secondary" icon={Phone} fullWidth onPress={() => router.push('/phone')} /> : null}
        </View>
      }
    >
      <View style={styles.ring}>
        <ProgressRing value={percent} label={`${percent}% ${t('mobile.onboarding.score.label')}`} size={160} color="action" track="border">
          <Text variant="display">{`${percent}%`}</Text>
          <Text variant="caption" tone="muted">
            {t('mobile.onboarding.score.label')}
          </Text>
        </ProgressRing>
      </View>
      <Text variant="title" heading style={styles.center}>
        {percent >= 50 ? t('mobile.onboarding.score.strong') : t('mobile.onboarding.score.start')}
      </Text>
      {topics.length > 0 ? (
        <Card>
          {topics.map(({ topic, level }) => (
            <View key={topic} style={styles.topic}>
              <Text weight="semibold" style={styles.flex}>
                {t(`mobile.onboarding.topics.${topic}`)}
              </Text>
              <Chip label={t(`mobile.onboarding.levels.${level}`)} tone={levelTone[level]} />
            </View>
          ))}
        </Card>
      ) : null}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  footer: { gap: theme.space.sm, paddingHorizontal: theme.m.gutter, paddingBottom: theme.space.xl },
  ring: { alignItems: 'center', marginTop: theme.space.lg },
  center: { textAlign: 'center' },
  topic: { flexDirection: 'row', alignItems: 'center', gap: theme.space.md, minHeight: theme.touch - theme.space.sm },
  flex: { flex: 1 },
  apple: { height: theme.m.buttonHeight, width: '100%' },
}));
