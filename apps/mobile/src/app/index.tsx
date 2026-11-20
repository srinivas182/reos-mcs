import { router } from 'expo-router';
import { Palette, Settings } from 'lucide-react-native';
import { useEffect, useState } from 'react';
import { ActivityIndicator } from 'react-native';
import { useTranslation } from 'react-i18next';
import { useUnistyles } from 'react-native-unistyles';

import { fetchHealth, type Health } from '@/lib/api';
import { Button, Card, ListRow, Screen, Text } from '@/ui';

type State = { kind: 'loading' } | { kind: 'ok'; health: Health } | { kind: 'error'; message: string };

/**
 * Placeholder home until onboarding (Sprint 3): the REOS brand, a live check
 * of the platform API, and links to Display settings and (in development) the
 * component gallery.
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
    <Screen>
      <Text variant="caption" weight="bold" tone="action">
        {t('mobile.eyebrow')}
      </Text>
      <Text variant="display">{t('common.appName')}</Text>
      <Text variant="bodyLarge" tone="body">
        {t('mobile.tagline')}
      </Text>

      <Card accessibilityLiveRegion="polite">
        {state.kind === 'loading' && <ActivityIndicator color={theme.colors.action} />}
        {state.kind === 'ok' && <Text>{t('mobile.connected', { app: state.health.app, version: state.health.version })}</Text>}
        {state.kind === 'error' && (
          <>
            <Text>{t('mobile.unreachable')}</Text>
            <Button label={t('common.tryAgain')} onPress={retry} />
          </>
        )}
      </Card>

      <ListRow icon={Settings} tone="neutral" title={t('mobile.displaySettings')} subtitle={t('mobile.displaySettingsHint')} onPress={() => router.push('/settings')} />
      {__DEV__ ? <ListRow icon={Palette} tone="info" title="Component gallery" subtitle="Development builds only" onPress={() => router.push('/design')} /> : null}
    </Screen>
  );
}
