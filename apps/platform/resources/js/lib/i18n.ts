import { createI18n, toLocale } from '@reos/i18n';

/**
 * The web app's i18next instance. Starts in the language the server rendered
 * on `<html lang>` (the person's saved choice, device cookie or browser
 * language), so the first paint is already in the right language.
 */
export const i18n = createI18n(
    toLocale(
        typeof document === 'undefined'
            ? undefined
            : document.documentElement.lang,
    ),
);
