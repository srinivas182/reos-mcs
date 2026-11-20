import { router } from 'expo-router';
import { useEffect, useState } from 'react';
import { ActivityIndicator, ScrollView, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';

import { fetchHealth, type Health } from '@/lib/api';
import { Button, Text } from '@/ui';

type State = { kind: 'loading' } | { kind: 'ok'; health: Health } | { kind: 'error'; message: string };

/**
 * Sprint 1–2 placeholder home screen: shows the REOS brand, proves the app can
 * reach the platform API, and opens display settings. Replaced by onboarding
 * in Sprint 3.
 */
export default function HomeScreen() {
  const { t } = useTranslation();
  const { theme } = useUnistyles();
  const [state, setState] = useState<State>({ kind: 'loading' });
  const [attempt, setAttempt] = useState(0);

  useEffect(() => {
    let active = true;

    fetchHealth()
      .then((health) => active && setState({ kind: 'ok', health }))
      .catch((error: unknown) => {
        if (active) {
          setState({ kind: 'error', message: error instanceof Error ? error.message : 'Unknown error' });
        }
      });

    return () => {
      active = false;
    };
  }, [attempt]);

  const retry = () => {
    setState({ kind: 'loading' });
    setAttempt((n) => n + 1);
  };

  return (
    <ScrollView contentContainerStyle={styles.screen}>
      <View style={styles.hero}>
        <Text variant="caption" weight="bold" tone="action" style={styles.eyebrow}>
          {t('mobile.eyebrow')}
        </Text>
        <Text variant="display">{t('common.appName')}</Text>
        <Text variant="bodyLarge" tone="body">
          {t('mobile.tagline')}
        </Text>
      </View>

      <View style={styles.card} accessibilityLiveRegion="polite">
        {state.kind === 'loading' && <ActivityIndicator color={theme.colors.action} />}
        {state.kind === 'ok' && <Text>{t('mobile.connected', { app: state.health.app, version: state.health.version })}</Text>}
        {state.kind === 'error' && (
          <>
            <Text>{t('mobile.unreachable')}</Text>
            <Button label={t('common.tryAgain')} onPress={retry} />
          </>
        )}
      </View>

      <Button
        label={t('mobile.displaySettings')}
        accessibilityHint={t('mobile.displaySettingsHint')}
        variant="outline"
        onPress={() => router.push('/settings')}
      />
      {__DEV__ ? <Button label="Component gallery (dev)" variant="ghost" onPress={() => router.push('/design')} /> : null}
    </ScrollView>
  );
}

const styles = StyleSheet.create((theme, rt) => ({
  screen: {
    flexGrow: 1,
    gap: theme.space.xl,
    paddingTop: rt.insets.top + theme.space.xxxl,
    paddingBottom: rt.insets.bottom + theme.space.xl,
    paddingHorizontal: theme.space.xl,
    backgroundColor: theme.colors.page,
  },
  hero: { gap: theme.space.md },
  eyebrow: { letterSpacing: 1 },
  card: {
    gap: theme.space.md,
    padding: theme.space.lg,
    minHeight: 72,
    justifyContent: 'center',
    borderRadius: theme.radius.lg,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: theme.colors.surface,
  },
}));
