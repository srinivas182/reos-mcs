/**
 * Unistyles themes built from @reos/tokens, so the phone app matches the web
 * app exactly: same colours (light, dark, calm high contrast), type, spacing,
 * radius and 48-point touch targets.
 *
 * `scale` is the person's text-size choice (Standard 1, Large 1.15, Largest
 * 1.3). React Native's own font scaling (the phone's text setting) applies on
 * top of it automatically.
 */
import {
  fontSize,
  fontWeight,
  lineHeight,
  minTouchTarget,
  radius,
  spacing,
  themes as tokenThemes,
  type SemanticColors,
  type ThemeName,
} from '@reos/tokens';

export type Theme = {
  name: ThemeName;
  colors: SemanticColors;
  space: typeof spacing;
  radius: typeof radius;
  type: { size: typeof fontSize; lineHeight: typeof lineHeight; weight: typeof fontWeight };
  touch: number;
  /** The person's text-size multiplier. */
  scale: number;
  /** A font size or line height scaled by the text-size choice (rounded to whole points). */
  font: (points: number) => number;
};

const build = (name: ThemeName, scale = 1): Theme => ({
  name,
  colors: tokenThemes[name],
  space: spacing,
  radius,
  type: { size: fontSize, lineHeight, weight: fontWeight },
  touch: minTouchTarget,
  scale,
  font: (points) => Math.round(points * scale),
});

export const appThemes = {
  light: build('light'),
  dark: build('dark'),
  contrast: build('contrast'),
};

export const breakpoints = {
  xs: 0,
  sm: 380,
  md: 600,
  lg: 900,
} as const;

/** A copy of a theme with a different text-size multiplier. */
export const withScale = (theme: Theme, scale: number): Theme => build(theme.name, scale);

type AppThemes = typeof appThemes;
type AppBreakpoints = typeof breakpoints;

// Unistyles' documented way to register theme and breakpoint types: empty
// interfaces that extend ours (declaration merging), so the lint rule doesn't apply.
declare module 'react-native-unistyles' {
  // eslint-disable-next-line @typescript-eslint/no-empty-object-type
  export interface UnistylesThemes extends AppThemes {}
  // eslint-disable-next-line @typescript-eslint/no-empty-object-type
  export interface UnistylesBreakpoints extends AppBreakpoints {}
}
