import { Lock } from 'lucide-react-native';
import { useEffect } from 'react';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { useAuth } from '@/lib/auth';
import { Button, Icon, Screen, Text } from '@/ui';

/** Reopening REOS with Face ID or fingerprint turned on. */
export default function LockScreen() {
  const { t } = useTranslation();
  const { unlock, signOut } = useAuth();

  // Ask straight away; the button is there if they cancel.
  useEffect(() => {
    void unlock();
  }, [unlock]);

  return (
    <Screen
      footer={
        <View style={styles.footer}>
          <Button label={t('mobile.biometric.unlock')} fullWidth onPress={() => void unlock()} />
          <Button label={t('mobile.biometric.otherWay')} variant="secondary" fullWidth onPress={() => void signOut()} />
        </View>
      }
    >
      <View style={styles.center}>
        <View style={styles.tile}>
          <Icon icon={Lock} color="action" size={36} />
        </View>
        <Text variant="headline" accessibilityRole="header">
          {t('mobile.biometric.locked')}
        </Text>
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  center: { alignItems: 'center', gap: theme.space.lg, marginTop: theme.space.xxl },
  tile: { width: 88, height: 88, borderRadius: theme.m.radius.hero, backgroundColor: theme.colors.actionTint, alignItems: 'center', justifyContent: 'center' },
  footer: { gap: theme.space.md },
}));
