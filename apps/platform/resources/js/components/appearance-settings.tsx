import { localeNames, supportedLocales } from '@reos/i18n';
import { Contrast, Languages, Monitor, Moon, Sun } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { ChoiceTiles } from '@/components/choice-tiles';
import type { ChoiceTile } from '@/components/choice-tiles';
import type { Appearance, Locale, TextSize } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';

const themeIcons = {
    system: Monitor,
    light: Sun,
    dark: Moon,
    contrast: Contrast,
} as const;
const themes: readonly Appearance[] = ['system', 'light', 'dark', 'contrast'];
const sizes: readonly TextSize[] = ['standard', 'large', 'largest'];
const scales: Record<TextSize, string> = {
    standard: '1',
    large: '1.15',
    largest: '1.3',
};

/** Sizes are previewed relative to the current size, so the tiles stay readable whichever is active. */
const sample = (scale: string) => (
    <span
        aria-hidden
        className="mt-2 font-semibold text-foreground"
        style={{ fontSize: `calc(1rem * ${scale})` }}
    >
        Aa
    </span>
);

/** Theme, text size and language. Changes apply instantly and are saved to the account. */
export default function AppearanceSettings() {
    const { t } = useTranslation();
    const {
        appearance,
        textSize,
        locale,
        updateAppearance,
        updateTextSize,
        updateLocale,
    } = useAppearance();

    const themeOptions: ChoiceTile<Appearance>[] = themes.map((value) => ({
        value,
        label: t(`appearance.themes.${value}.label`),
        description: t(`appearance.themes.${value}.description`),
        icon: themeIcons[value],
    }));

    const sizeOptions: ChoiceTile<TextSize>[] = sizes.map((value) => ({
        value,
        label: t(`appearance.textSizes.${value}.label`),
        description: t(`appearance.textSizes.${value}.description`),
        preview: sample(scales[value]),
    }));

    // Each language is named in its own language, so people can always find theirs.
    const languageOptions: ChoiceTile<Locale>[] = supportedLocales.map(
        (value) => ({
            value,
            label: localeNames[value],
            description: t(`appearance.languages.${value}.description`),
            icon: Languages,
        }),
    );

    return (
        <div className="space-y-8">
            <ChoiceTiles
                label={t('appearance.language')}
                value={locale}
                options={languageOptions}
                onChange={updateLocale}
            />
            <ChoiceTiles
                label={t('appearance.theme')}
                value={appearance}
                options={themeOptions}
                onChange={updateAppearance}
            />
            <ChoiceTiles
                label={t('appearance.textSize')}
                value={textSize}
                options={sizeOptions}
                onChange={updateTextSize}
            />
            <p className="text-caption text-muted-foreground">
                {t('appearance.savedNote')}
            </p>
        </div>
    );
}
