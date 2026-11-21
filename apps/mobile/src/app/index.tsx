import { router } from 'expo-router';
import { Palette } from 'lucide-react-native';
import { useEffect, useState } from 'react';
import { ActivityIndicator, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';

import { fetchHealth, type Health } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import { Avatar, Button, Card, ListRow, Screen, Text } from '@/ui';

type State = { kind: 'loading' } | { kind: 'ok'; health: Health } | { kind: 'error'; message: string };

/**
 * Placeholder home until Today is built (A3.3 onward): the REOS brand, your
 * initials (→ Me), a live check of the platform API and, in development,
 * the component gallery.
 */
export default function HomeScreen() {
  const { t } = useTranslation();
  const { theme } = useUnistyles();
  const { user } = useAuth();
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
      {/* Me opens from your initials, top right (journey screen 16). */}
      <View style={styles.top}>
        <Text variant="caption" weight="bold" tone="action">
          {t('mobile.eyebrow')}
        </Text>
        {user ? <Avatar name={user.name ?? user.email ?? user.phone ?? '?'} label={t('mobile.me.open')} onPress={() => router.push('/me')} /> : null}
      </View>
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

      {__DEV__ ? <ListRow icon={Palette} tone="info" title="Component gallery" subtitle="Development builds only" onPress={() => router.push('/design')} /> : null}
    </Screen>
  );
}

const styles = StyleSheet.create({
  top: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
});
