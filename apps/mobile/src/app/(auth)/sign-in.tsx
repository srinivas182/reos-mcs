import * as AppleAuthentication from 'expo-apple-authentication';
import { router } from 'expo-router';
import { Phone } from 'lucide-react-native';
import { useEffect, useState } from 'react';
import { Platform, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';

import { useAuth, type Outcome, type SignInOptions } from '@/lib/auth';
import { appConfig } from '@/lib/monitoring';
import { Button, Callout, Screen, Text } from '@/ui';

/**
 * Sign in (journey screen SH/signin): Apple, Google or phone, no passwords.
 * Any of them creates a free account the first time. Buttons come from the
 * server, so switching a provider on needs no app release.
 */
export default function SignInScreen() {
  const { t } = useTranslation();
  const { theme } = useUnistyles();
  const { signInWithApple, signInWithBrowser } = useAuth();
  const [options, setOptions] = useState<SignInOptions | null>(null);
  const [busy, setBusy] = useState<'apple' | 'google' | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let active = true;
    void appConfig().then((config) => active && setOptions(config?.sign_in ?? { google: false, apple: false, phone: false, demo: false }));

    return () => {
      active = false;
    };
  }, []);

  const run = async (provider: 'apple' | 'google', attempt: () => Promise<Outcome>) => {
    setBusy(provider);
    setError(null);
    const outcome = await attempt();
    setBusy(null);

    if (!outcome.ok && 'message' in outcome) {
      setError(outcome.message);
    }
  };

  const none = options !== null && !options.apple && !options.google && !options.phone;
  // Apple's own button on iPhone (its design rules); the browser flow elsewhere and on demo data.
  const nativeApple = Platform.OS === 'ios' && options?.apple === true && !options.demo;

  return (
    <Screen footer={<Text variant="caption" tone="muted" style={styles.footer}>{t('mobile.signIn.newHere')}</Text>}>
      <View style={styles.brand} accessibilityElementsHidden importantForAccessibility="no-hide-descendants">
        <Text variant="title" weight="bold" tone="onAction">
          RE
        </Text>
      </View>
      <Text variant="headline" accessibilityRole="header">
        {t('mobile.signIn.welcomeBack')}
      </Text>
      <Text tone="muted">{t('mobile.signIn.subtitle')}</Text>

      {error ? <Callout tone="danger" title={error} live /> : null}
      {none ? <Callout tone="warning" title={t('mobile.signIn.unavailable')} /> : null}

      <View style={styles.buttons}>
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
        {options?.phone ? <Button label={t('mobile.signIn.phone')} variant="secondary" icon={Phone} fullWidth onPress={() => router.push('/phone')} /> : null}
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  brand: {
    width: theme.m.avatar + 8,
    height: theme.m.avatar + 8,
    borderRadius: theme.m.radius.tile,
    backgroundColor: theme.colors.action,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: theme.space.xl,
  },
  buttons: { gap: theme.space.md, marginTop: theme.space.md },
  apple: { height: theme.m.buttonHeight, width: '100%' },
  footer: { textAlign: 'center' },
}));
