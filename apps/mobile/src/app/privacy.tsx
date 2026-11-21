import { router } from 'expo-router';
import { Smartphone, Trash2 } from 'lucide-react-native';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { biometricAvailable } from '@/lib/auth';
import { enableBiometric } from '@/lib/biometric';
import { biometricEnabled, setBiometricEnabled } from '@/lib/session';
import { Eyebrow, MenuGroup, MenuRow, Screen, SwitchRow, TitleBar } from '@/ui';

/**
 * Privacy and my data (mobile journey screen 153), from Me. Face ID can be
 * turned off here (screen 189); Delete account is "Me → Privacy → Delete
 * account" (screen 197). Connected tools, what your broker can see and
 * Download my data arrive with connections (B3.1) and data export.
 */
export default function PrivacyScreen() {
  const { t } = useTranslation();
  const [biometric, setBiometric] = useState<boolean | null>(null);

  useEffect(() => {
    void (async () => setBiometric((await biometricAvailable()) ? await biometricEnabled() : null))();
  }, []);

  const toggleBiometric = async (on: boolean) => {
    if (on) {
      setBiometric(await enableBiometric());
    } else {
      await setBiometricEnabled(false);
      setBiometric(false);
    }
  };

  return (
    <Screen>
      <TitleBar title={t('mobile.privacy.title')} onBack={() => router.back()} />
      <Eyebrow>{t('mobile.privacy.security')}</Eyebrow>
      <MenuGroup>
        <MenuRow icon={Smartphone} title={t('mobile.account.devices')} onPress={() => router.push('/devices')} />
        {biometric === null ? null : (
          <SwitchRow label={t('mobile.biometric.setting')} description={t('mobile.biometric.settingHint')} value={biometric} onValueChange={(on) => void toggleBiometric(on)} />
        )}
      </MenuGroup>
      <MenuGroup>
        <MenuRow icon={Trash2} title={t('mobile.account.delete')} destructive onPress={() => router.push('/delete-account')} />
      </MenuGroup>
    </Screen>
  );
}
