/**
 * Theme, text-size and language preferences on the phone.
 *
 * Saved on the device (MMKV) and applied through the Unistyles runtime, so
 * every screen restyles instantly without re-rendering. From Sprint 3 the
 * signed-in person's account settings (the same ones as the web app) win.
 */
import { isLocale, type Locale } from '@reos/i18n';
import { textScale, type TextSize } from '@reos/tokens';
import { createMMKV } from 'react-native-mmkv';
import { UnistylesRuntime } from 'react-native-unistyles';
import { useSyncExternalStore } from 'react';

import { i18n } from '@/lib/i18n';

import { withScale } from './theme';

export type Appearance = 'system' | 'light' | 'dark' | 'contrast';
export type Preferences = { appearance: Appearance; textSize: TextSize; locale: Locale | 'device' };

const storage = createMMKV({ id: 'reos.preferences' });
const KEY = 'preferences';
const defaults: Preferences = { appearance: 'system', textSize: 'standard', locale: 'device' };

const isAppearance = (v: unknown): v is Appearance => v === 'system' || v === 'light' || v === 'dark' || v === 'contrast';
const isTextSize = (v: unknown): v is TextSize => typeof v === 'string' && v in textScale;

/** Reads saved preferences, ignoring anything malformed. */
export function load(): Preferences {
  try {
    const saved = JSON.parse(storage.getString(KEY) ?? '{}') as Partial<Record<keyof Preferences, unknown>>;

    return {
      appearance: isAppearance(saved.appearance) ? saved.appearance : defaults.appearance,
      textSize: isTextSize(saved.textSize) ? saved.textSize : defaults.textSize,
      locale: saved.locale === 'device' || isLocale(saved.locale) ? saved.locale : defaults.locale,
    };
  } catch {
    return defaults;
  }
}

let state: Preferences = defaults;
const listeners = new Set<() => void>();

/** Pushes preferences into Unistyles and i18n. */
function apply(next: Preferences, deviceLocale: Locale): void {
  if (next.appearance === 'system') {
    UnistylesRuntime.setAdaptiveThemes(true);
  } else {
    UnistylesRuntime.setAdaptiveThemes(false);
    UnistylesRuntime.setTheme(next.appearance);
  }

  const scale = textScale[next.textSize];
  for (const name of ['light', 'dark', 'contrast'] as const) {
    UnistylesRuntime.updateTheme(name, (theme) => withScale(theme, scale));
  }

  const locale = next.locale === 'device' ? deviceLocale : next.locale;
  if (i18n.language !== locale) {
    void i18n.changeLanguage(locale);
  }
}

let deviceLocale: Locale = 'en';

/** Loads and applies saved preferences. Call once at start-up. */
export function initPreferences(phoneLocale: Locale): void {
  deviceLocale = phoneLocale;
  state = load();
  apply(state, deviceLocale);
}

/** Changes one or more preferences, applies them and saves them. */
export function setPreferences(change: Partial<Preferences>): void {
  state = { ...state, ...change };
  storage.set(KEY, JSON.stringify(state));
  apply(state, deviceLocale);
  listeners.forEach((listener) => listener());
}

export function usePreferences(): Preferences {
  return useSyncExternalStore(
    (listener) => {
      listeners.add(listener);
      return () => listeners.delete(listener);
    },
    () => state,
    () => state,
  );
}
