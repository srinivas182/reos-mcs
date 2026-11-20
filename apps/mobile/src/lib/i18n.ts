import { createI18n, toLocale } from '@reos/i18n';
import { getLocales } from 'expo-localization';

/**
 * The app's i18next instance. Starts in the phone's language (English or
 * Spanish, else English). From Sprint 3 the signed-in person's saved language
 * wins, the same as on the web.
 */
export const i18n = createI18n(toLocale(getLocales()[0]?.languageTag));
