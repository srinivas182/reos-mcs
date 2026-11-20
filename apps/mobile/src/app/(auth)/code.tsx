import { router, useLocalSearchParams } from 'expo-router';
import { ArrowLeft, ShieldCheck } from 'lucide-react-native';
import { useEffect, useState } from 'react';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { useAuth } from '@/lib/auth';
import { maskPhone } from '@/lib/phone';
import { Button, Callout, CodeInput, Icon, IconButton, Screen, ScreenHeader, Text } from '@/ui';

const RESEND_SECONDS = 30;

/** Phone sign-in, step 2: the six-digit code (journey screen SH/otp). */
export default function CodeScreen() {
  const { t } = useTranslation();
  const { phone } = useLocalSearchParams<{ phone: string }>();
  const { verifyPhoneCode, sendPhoneCode } = useAuth();
  const [code, setCode] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [seconds, setSeconds] = useState(RESEND_SECONDS);

  useEffect(() => {
    if (seconds <= 0) {
      return;
    }

    const timer = setTimeout(() => setSeconds((value) => value - 1), 1000);

    return () => clearTimeout(timer);
  }, [seconds]);

  const verify = async (value: string) => {
    setBusy(true);
    setError(null);
    const outcome = await verifyPhoneCode(phone, value);
    setBusy(false);

    if (!outcome.ok && 'message' in outcome) {
      setError(outcome.message);
      setCode('');
    }
  };

  const change = (value: string) => {
    setCode(value);

    // Submit as soon as the sixth digit arrives (typed or auto-filled).
    if (value.length === 6 && !busy) {
      void verify(value);
    }
  };

  const resend = async () => {
    const result = await sendPhoneCode(phone);
    setSeconds(RESEND_SECONDS);
    setNotice(result.ok ? t('mobile.signIn.code.sent') : result.message);
  };

  return (
    <Screen>
      <IconButton icon={ArrowLeft} label={t('common.back')} onPress={() => router.back()} />
      <ScreenHeader title={t('mobile.signIn.code.title')} />
      <Text tone="muted">{t('mobile.signIn.code.sentTo', { phone: maskPhone(phone) })}</Text>
      <CodeInput label={t('mobile.signIn.code.label')} value={code} onChange={change} invalid={error !== null} />
      <Text variant="caption" tone="muted" style={styles.center}>
        {t('mobile.signIn.code.autofill')}
      </Text>
      {error ? <Callout tone="danger" title={error} live /> : null}
      {notice ? <Callout tone="success" title={notice} live /> : null}
      {seconds > 0 ? (
        <Text tone="action" weight="medium" style={styles.center} accessibilityLiveRegion="none">
          {t('mobile.signIn.code.resendIn', { seconds: String(seconds).padStart(2, '0') })}
        </Text>
      ) : (
        <Button label={t('mobile.signIn.code.resend')} variant="ghost" onPress={() => void resend()} />
      )}
      <View style={styles.why} accessible>
        <Icon icon={ShieldCheck} color="textMuted" size={20} />
        <Text variant="caption" tone="body" style={styles.whyText}>
          {t('mobile.signIn.code.why')}
        </Text>
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  center: { textAlign: 'center' },
  why: {
    flexDirection: 'row',
    gap: theme.space.md,
    alignItems: 'center',
    padding: theme.space.lg,
    borderRadius: theme.m.radius.card,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: theme.colors.surface,
  },
  whyText: { flex: 1 },
}));
