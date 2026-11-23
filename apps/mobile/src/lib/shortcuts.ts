/**
 * The app's home-screen shortcut (B4.3b): press and hold the REOS icon, then
 * "Add a to-do" opens voice capture (`/voice`). Shown only when signed in,
 * in the language on screen. "Quiz me" opens the hands-free voice quiz
 * (A9.3), as does the Siri App Shortcut from plugins/with-quiz-shortcut.js.
 */
import * as QuickActions from 'expo-quick-actions';
import { useQuickActionRouting } from 'expo-quick-actions/router';
import { useEffect } from 'react';
import { Platform } from 'react-native';
import { useTranslation } from 'react-i18next';

export const VOICE_SHORTCUT = 'reos.voice';
export const QUIZ_SHORTCUT = 'reos.quiz';

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
            // Hands-free voice quiz (A9.3, screen 51); "Hey Siri, quiz me with REOS" opens the same screen.
            {
              id: QUIZ_SHORTCUT,
              title: t('practice.handsFree.shortcut'),
              subtitle: t('practice.handsFree.shortcutSubtitle'),
              icon: Platform.OS === 'ios' ? 'symbol:headphones' : null,
              params: { href: '/practice/hands-free' },
            },
          ]
        : [],
    ).catch(() => undefined);
  }, [signedIn, t, i18n.language]);

  // Signed out: sign-in comes first, so the shortcut does nothing.
  useQuickActionRouting(() => !signedIn);
}
