import { router } from 'expo-router';
import { useState } from 'react';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { Button, Callout, Screen, TextField, TitleBar } from '@/ui';

/**
 * Enter a code (mobile journey screen 11). A school, brokerage or
 * association code unlocks a sponsored seat. The screen is built now;
 * codes and seats are checked from A4.3 (invites and seats).
 */
export default function EnterCodeScreen() {
  const { t } = useTranslation();
  const [code, setCode] = useState('');
  const [sent, setSent] = useState(false);

  return (
    <Screen footer={<View style={styles.footer}><Button label={t('onboarding.code.continue')} disabled={code.trim().length < 4} fullWidth onPress={() => setSent(true)} /></View>}>
      <TitleBar title={t('onboarding.code.title')} onBack={() => router.back()} />
      <TextField
        label={t('onboarding.code.label')}
        placeholder={t('onboarding.code.placeholder')}
        value={code}
        onChangeText={(value) => {
          setCode(value.toUpperCase());
          setSent(false);
        }}
        autoCapitalize="characters"
        autoCorrect={false}
        style={styles.code}
      />
      {sent ? <Callout tone="info" live title={t('onboarding.code.soon')} /> : null}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  footer: { paddingHorizontal: theme.m.gutter, paddingBottom: theme.space.xl },
  code: { textAlign: 'center', letterSpacing: 4, fontSize: theme.font(theme.type.size.title), fontWeight: '700' },
}));
