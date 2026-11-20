import { localeNames, supportedLocales, type Locale } from '@reos/i18n';
import type { TextSize } from '@reos/tokens';
import { router } from 'expo-router';
import { ArrowLeft, LogOut, Smartphone, Trash2 } from 'lucide-react-native';
import { useEffect, useState } from 'react';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { biometricAvailable, useAuth } from '@/lib/auth';
import { enableBiometric } from '@/lib/biometric';
import { biometricEnabled, setBiometricEnabled } from '@/lib/session';

import { Button, IconButton, ListRow, OptionGroup, Screen, ScreenHeader, SectionTitle, setPreferences, SwitchRow, usePreferences, type Appearance, type Option } from '@/ui';

const appearances: readonly Appearance[] = ['system', 'light', 'dark', 'contrast'];
const sizes: readonly TextSize[] = ['standard', 'large', 'largest'];

/**
 * Me › Settings: the account (devices, Face ID, delete, sign out), then
 * display and language as option rows (journey settings pattern).
 */
export default function SettingsScreen() {
  const { t } = useTranslation();
  const preferences = usePreferences();
  const { signOut } = useAuth();
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

  const themeOptions: Option<Appearance>[] = appearances.map((value) => ({
    value,
    label: t(`appearance.themes.${value}.label`),
    description: t(`appearance.themes.${value}.description`),
  }));
  const sizeOptions: Option<TextSize>[] = sizes.map((value) => ({
    value,
    label: t(`appearance.textSizes.${value}.label`),
    description: t(`appearance.textSizes.${value}.description`),
  }));
  const languageOptions: Option<Locale | 'device'>[] = [
    { value: 'device', label: t('appearance.languages.device.label'), description: t('appearance.languages.device.description') },
    ...supportedLocales.map((value) => ({ value, label: localeNames[value], description: t(`appearance.languages.${value}.description`) })),
  ];

  return (
    <Screen>
      <IconButton icon={ArrowLeft} label={t('common.close')} onPress={() => router.back()} />
      <ScreenHeader title={t('mobile.account.title')} />
      <View style={rowStyles.list}>
        <ListRow icon={Smartphone} title={t('mobile.account.devices')} onPress={() => router.push('/devices')} />
        <ListRow icon={Trash2} tone="danger" title={t('mobile.account.delete')} onPress={() => router.push('/delete-account')} />
      </View>
      {biometric === null ? null : (
        <SwitchRow label={t('mobile.biometric.setting')} description={t('mobile.biometric.settingHint')} value={biometric} onValueChange={(on) => void toggleBiometric(on)} />
      )}
      <Button label={t('mobile.account.signOut')} variant="secondary" icon={LogOut} fullWidth onPress={() => void signOut()} />
      <SectionTitle>{t('mobile.account.display')}</SectionTitle>
      <SectionTitle>{t('appearance.language')}</SectionTitle>
      <OptionGroup label={t('appearance.language')} options={languageOptions} value={preferences.locale} onChange={(locale) => setPreferences({ locale })} />
      <SectionTitle>{t('appearance.theme')}</SectionTitle>
      <OptionGroup label={t('appearance.theme')} options={themeOptions} value={preferences.appearance} onChange={(appearance) => setPreferences({ appearance })} />
      <SectionTitle>{t('appearance.textSize')}</SectionTitle>
      <OptionGroup label={t('appearance.textSize')} options={sizeOptions} value={preferences.textSize} onChange={(textSize) => setPreferences({ textSize })} />
    </Screen>
  );
}

const rowStyles = StyleSheet.create((theme) => ({
  list: { gap: theme.space.md },
}));
