/**
 * The app's home-screen shortcut (B4.3b): press and hold the REOS icon, then
 * "Add a to-do" opens voice capture (`/voice`). Shown only when signed in,
 * in the language on screen. Siri App Intents come later (ADR 0018).
 */
import * as QuickActions from 'expo-quick-actions';
import { useQuickActionRouting } from 'expo-quick-actions/router';
import { useEffect } from 'react';
import { Platform } from 'react-native';
import { useTranslation } from 'react-i18next';

export const VOICE_SHORTCUT = 'reos.voice';

export function useShortcuts(signedIn: boolean): void {
  const { t, i18n } = useTranslation();

  useEffect(() => {
    void QuickActions.setItems(
      signedIn
        ? [
            {
              id: VOICE_SHORTCUT,
              title: t('voice.shortcut'),
              subtitle: t('voice.shortcutSubtitle'),
              icon: Platform.OS === 'ios' ? 'symbol:mic.fill' : null,
              params: { href: '/voice' },
            },
          ]
        : [],
    ).catch(() => undefined);
  }, [signedIn, t, i18n.language]);

  // Signed out: sign-in comes first, so the shortcut does nothing.
  useQuickActionRouting(() => !signedIn);
}
