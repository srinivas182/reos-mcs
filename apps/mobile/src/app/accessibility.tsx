import { localeNames, supportedLocales, type Locale } from '@reos/i18n';
import type { TextSize } from '@reos/tokens';
import { router } from 'expo-router';
import { useTranslation } from 'react-i18next';

import { Eyebrow, MenuGroup, PillGroup, Screen, setPreferences, SwitchRow, Text, TextSizeControl, TitleBar, usePreferences } from '@/ui';

const sizes: readonly TextSize[] = ['standard', 'large', 'largest'];

/**
 * Language and accessibility (mobile journey screen 192), from Me: English
 * or Español, text size, high contrast. Light or dark follows the phone.
 * Captions and read-aloud arrive with lessons; bold text and reduce motion
 * follow the phone's own settings.
 */
export default function AccessibilityScreen() {
  const { t, i18n } = useTranslation();
  const preferences = usePreferences();
  const language = (preferences.locale === 'device' ? i18n.language : preferences.locale) as Locale;

  return (
    <Screen>
      <TitleBar title={t('mobile.accessibility.title')} onBack={() => router.back()} />

      <Eyebrow>{t('mobile.accessibility.language')}</Eyebrow>
      <PillGroup
        label={t('mobile.accessibility.language')}
        value={language}
        options={supportedLocales.map((value) => ({ value, label: localeNames[value] }))}
        onChange={(locale) => setPreferences({ locale })}
      />

      <Eyebrow>{t('mobile.accessibility.textSize')}</Eyebrow>
      <TextSizeControl
        label={t('mobile.accessibility.textSize')}
        value={preferences.textSize}
        steps={sizes.map((value) => ({ value, label: t(`appearance.textSizes.${value}.label`) }))}
        onChange={(textSize) => setPreferences({ textSize })}
      />

      <MenuGroup>
        <SwitchRow
          label={t('mobile.accessibility.highContrast')}
          description={t('mobile.accessibility.highContrastHint')}
          value={preferences.appearance === 'contrast'}
          onValueChange={(on) => setPreferences({ appearance: on ? 'contrast' : 'system' })}
        />
      </MenuGroup>

      <Text variant="caption" tone="muted">
        {t('mobile.accessibility.note')}
      </Text>
    </Screen>
  );
}
