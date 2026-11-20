/**
 * Registers the REOS themes with Unistyles. Imported once, first thing, by the
 * root layout (before any StyleSheet is created).
 */
import { StyleSheet } from 'react-native-unistyles';

import { appThemes, breakpoints } from './theme';

StyleSheet.configure({
  themes: appThemes,
  breakpoints,
  settings: {
    // Follows the phone's light/dark setting until the person picks a theme.
    adaptiveThemes: true,
  },
});
