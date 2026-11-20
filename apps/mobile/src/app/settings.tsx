import { localeNames, supportedLocales, type Locale } from '@reos/i18n';
import type { TextSize } from '@reos/tokens';
import { router } from 'expo-router';
import { ArrowLeft } from 'lucide-react-native';
import { useTranslation } from 'react-i18next';

import { IconButton, OptionGroup, Screen, ScreenHeader, SectionTitle, setPreferences, usePreferences, type Appearance, type Option } from '@/ui';

const appearances: readonly Appearance[] = ['system', 'light', 'dark', 'contrast'];
const sizes: readonly TextSize[] = ['standard', 'large', 'largest'];

/** Display and language: theme, text size and language, as option rows (journey settings pattern). */
export default function SettingsScreen() {
  const { t } = useTranslation();
  const preferences = usePreferences();

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
      <ScreenHeader title={t('appearance.pageTitle')} />
      <SectionTitle>{t('appearance.language')}</SectionTitle>
      <OptionGroup label={t('appearance.language')} options={languageOptions} value={preferences.locale} onChange={(locale) => setPreferences({ locale })} />
      <SectionTitle>{t('appearance.theme')}</SectionTitle>
      <OptionGroup label={t('appearance.theme')} options={themeOptions} value={preferences.appearance} onChange={(appearance) => setPreferences({ appearance })} />
      <SectionTitle>{t('appearance.textSize')}</SectionTitle>
      <OptionGroup label={t('appearance.textSize')} options={sizeOptions} value={preferences.textSize} onChange={(textSize) => setPreferences({ textSize })} />
    </Screen>
  );
}
