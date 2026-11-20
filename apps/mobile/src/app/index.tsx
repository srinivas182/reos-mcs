import { useEffect, useState } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { colors, fontSize, minTouchTarget, radius, spacing } from '@reos/tokens';

import { fetchHealth, type Health } from '@/lib/api';

type State =
  | { kind: 'loading' }
  | { kind: 'ok'; health: Health }
  | { kind: 'error'; message: string };

/**
 * Sprint 1 placeholder home screen: shows the REOS brand and proves the app
 * can reach the platform API. Replaced by onboarding in Sprint 3.
 */
export default function HomeScreen() {
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
    <SafeAreaView style={styles.screen}>
      <View style={styles.hero}>
        <Text style={styles.eyebrow} accessibilityRole="text">
          REAL ESTATE OS
        </Text>
        <Text style={styles.title} accessibilityRole="header">
          REOS
        </Text>
        <Text style={styles.subtitle}>
          Learn, get licensed, stay compliant and grow, all in one app.
        </Text>
      </View>

      <View style={styles.card} accessibilityLiveRegion="polite">
        {state.kind === 'loading' && <ActivityIndicator color={colors.sky} />}
        {state.kind === 'ok' && (
          <Text style={styles.status}>
            Connected to {state.health.app} · version {state.health.version}
          </Text>
        )}
        {state.kind === 'error' && (
          <>
            <Text style={styles.status}>Can&apos;t reach the REOS server right now.</Text>
            <Pressable
              accessibilityRole="button"
              onPress={retry}
              style={({ pressed }) => [styles.button, pressed && styles.buttonPressed]}>
              <Text style={styles.buttonText}>Try again</Text>
            </Pressable>
          </>
        )}
      </View>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  screen: {
    flex: 1,
    backgroundColor: colors.navy,
    paddingHorizontal: spacing.xl,
    justifyContent: 'space-between',
    paddingVertical: spacing.xxl,
  },
  hero: { marginTop: spacing.xxl * 2, gap: spacing.md },
  eyebrow: { color: colors.sky, fontSize: fontSize.caption, fontWeight: '700', letterSpacing: 1 },
  title: { color: colors.surface, fontSize: 56, fontWeight: '800' },
  subtitle: { color: '#CBD5E1', fontSize: fontSize.bodyLarge, lineHeight: 26 },
  card: {
    backgroundColor: '#1E293B',
    borderRadius: radius.lg,
    padding: spacing.lg,
    gap: spacing.md,
    minHeight: 72,
    justifyContent: 'center',
  },
  status: { color: colors.surface, fontSize: fontSize.body },
  button: {
    minHeight: minTouchTarget,
    borderRadius: radius.md,
    backgroundColor: colors.blue,
    alignItems: 'center',
    justifyContent: 'center',
  },
  buttonPressed: { opacity: 0.85 },
  buttonText: { color: colors.surface, fontSize: fontSize.body, fontWeight: '600' },
});
