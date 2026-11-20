import { router } from 'expo-router';
import { ScanFace } from 'lucide-react-native';
import { Platform, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { enableBiometric } from '@/lib/biometric';
import { setBiometricEnabled } from '@/lib/session';
import { Button, Icon, Screen, Text } from '@/ui';

/** Offered once per phone after the first sign-in (journey screen SH/faceid). */
export default function FaceIdScreen() {
  const { t } = useTranslation();
  const ios = Platform.OS === 'ios';

  const choose = async (use: boolean) => {
    if (use) {
      await enableBiometric();
    } else {
      await setBiometricEnabled(false);
    }

    router.replace('/');
  };

  return (
    <Screen
      footer={
        <View style={styles.footer}>
          <Button label={ios ? t('mobile.biometric.use') : t('mobile.biometric.useAndroid')} fullWidth onPress={() => void choose(true)} />
          <Button label={t('mobile.biometric.notNow')} variant="secondary" fullWidth onPress={() => void choose(false)} />
          {ios ? null : (
            <Text variant="caption" tone="muted" style={styles.center}>
              {t('mobile.biometric.androidNote')}
            </Text>
          )}
        </View>
      }
    >
      <View style={styles.hero}>
        <View style={styles.tile}>
          <Icon icon={ScanFace} color="action" size={40} />
        </View>
        <Text variant="headline" accessibilityRole="header" style={styles.center}>
          {ios ? t('mobile.biometric.title') : t('mobile.biometric.titleAndroid')}
        </Text>
        <Text tone="muted" style={styles.center}>
          {t('mobile.biometric.body')}
        </Text>
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  hero: { alignItems: 'center', gap: theme.space.lg, marginTop: theme.space.xxl },
  tile: { width: 88, height: 88, borderRadius: theme.m.radius.hero, backgroundColor: theme.colors.actionTint, alignItems: 'center', justifyContent: 'center' },
  center: { textAlign: 'center' },
  footer: { gap: theme.space.md },
}));
