import { localeNames, supportedLocales, type Locale } from '@reos/i18n';
import type { TextSize } from '@reos/tokens';
import { router } from 'expo-router';
import { ScrollView, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { Button, ChoiceTiles, setPreferences, Text, usePreferences, type Appearance, type ChoiceTile } from '@/ui';

const appearances: readonly Appearance[] = ['system', 'light', 'dark', 'contrast'];
const sizes: readonly TextSize[] = ['standard', 'large', 'largest'];

/** Display and language: the same choices as the web app's Appearance and language page. */
export default function SettingsScreen() {
  const { t } = useTranslation();
  const preferences = usePreferences();

  const themeOptions: ChoiceTile<Appearance>[] = appearances.map((value) => ({
    value,
    label: t(`appearance.themes.${value}.label`),
    description: t(`appearance.themes.${value}.description`),
  }));
  const sizeOptions: ChoiceTile<TextSize>[] = sizes.map((value) => ({
    value,
    label: t(`appearance.textSizes.${value}.label`),
    description: t(`appearance.textSizes.${value}.description`),
  }));
  const languageOptions: ChoiceTile<Locale | 'device'>[] = [
    { value: 'device', label: t('appearance.languages.device.label'), description: t('appearance.languages.device.description') },
    ...supportedLocales.map((value) => ({ value, label: localeNames[value], description: t(`appearance.languages.${value}.description`) })),
  ];

  return (
    <ScrollView contentContainerStyle={styles.screen}>
      <View style={styles.header}>
        <Button label={t('common.close')} variant="ghost" onPress={() => router.back()} />
      </View>
      <Text variant="headline">{t('appearance.pageTitle')}</Text>
      <ChoiceTiles label={t('appearance.language')} value={preferences.locale} options={languageOptions} onChange={(locale) => setPreferences({ locale })} />
      <ChoiceTiles label={t('appearance.theme')} value={preferences.appearance} options={themeOptions} onChange={(appearance) => setPreferences({ appearance })} />
      <ChoiceTiles label={t('appearance.textSize')} value={preferences.textSize} options={sizeOptions} onChange={(textSize) => setPreferences({ textSize })} />
    </ScrollView>
  );
}

const styles = StyleSheet.create((theme, rt) => ({
  screen: {
    gap: theme.space.xl,
    paddingTop: rt.insets.top + theme.space.md,
    paddingBottom: rt.insets.bottom + theme.space.xl,
    paddingHorizontal: theme.space.xl,
    backgroundColor: theme.colors.page,
  },
  header: { alignItems: 'flex-start', marginLeft: -theme.space.lg },
}));
