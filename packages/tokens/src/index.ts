/**
 * REOS design tokens (v1, Sprint 2.2).
 *
 * One source of truth for colour, type, space, shape and motion on web and
 * mobile. Values come from the journey documents Nelle approved.
 *
 * - `palette`: raw colours. Never used directly by components.
 * - `themes`: semantic colours per theme (light, dark, contrast). Components
 *   use only these, so every theme works everywhere.
 * - `scripts/build.ts` turns this file into CSS variables and a Tailwind 4
 *   theme for the web (src/generated/). Run `pnpm --filter @reos/tokens build`.
 *
 * Keep this a single file with no imports: the build script runs it directly
 * with Node's type stripping, and Metro bundles it as-is for mobile.
 */

/* ── Raw palette ───────────────────────────────────────────────────────────── */

export const palette = {
  navy950: '#0B1120',
  navy900: '#0F172A',
  slate800: '#1E293B',
  slate700: '#334155',
  slate600: '#475569',
  slate500: '#64748B',
  slate400: '#94A3B8',
  slate300: '#CBD5E1',
  slate200: '#E3E7EE',
  slate100: '#EEF1F5',
  slate50: '#F6F7F9',
  white: '#FFFFFF',
  black: '#000000',
  blue700: '#1E3FA3',
  blue600: '#2952CC',
  blue300: '#93C5FD',
  blue200: '#A9BEFF',
  blue100: '#E8EEFC',
  blue900: '#1A2650',
  green800: '#14532D',
  green700: '#15803D',
  green400: '#4ADE80',
  green50: '#F0FDF4',
  green950: '#052E16',
  amber900: '#78350F',
  amber700: '#B45309',
  amber300: '#FCD34D',
  amber100: '#FEF3C7',
  amber950: '#422006',
  red900: '#7F1D1D',
  red700: '#B42318',
  red400: '#F87171',
  red50: '#FEF3F2',
  red950: '#450A0A',
} as const;

/* ── Semantic themes ───────────────────────────────────────────────────────── */

export type ThemeName = 'light' | 'dark' | 'contrast';

export type SemanticColors = {
  /** App background behind cards. */
  page: string;
  /** Cards, sheets, inputs. */
  surface: string;
  /** Subtle fills: table stripes, chips, hover. */
  surfaceMuted: string;
  /** Headings and key numbers. */
  text: string;
  /** Body copy. */
  textBody: string;
  /** Secondary text and labels. Passes AA on page and surface. */
  textMuted: string;
  /** Decorative dividers (not used for control edges). */
  border: string;
  /** Edges of inputs and controls (≥ 3:1 against surface, WCAG 1.4.11). */
  borderControl: string;
  /** Primary action fill and links. */
  action: string;
  /** Pressed or hovered primary action. */
  actionPressed: string;
  /** Text and icons on `action`. */
  onAction: string;
  /** Selected rows, active nav, soft highlights. */
  actionTint: string;
  /** Keyboard focus ring. */
  focus: string;
  success: string;
  successSurface: string;
  warning: string;
  warningSurface: string;
  danger: string;
  dangerSurface: string;
  /** Text and icons on `danger`. */
  onDanger: string;
  /** Overlay behind dialogs and sheets. */
  scrim: string;
};

export const themes: Record<ThemeName, SemanticColors> = {
  light: {
    page: palette.slate50,
    surface: palette.white,
    surfaceMuted: palette.slate100,
    text: palette.navy900,
    textBody: palette.slate700,
    textMuted: palette.slate600,
    border: palette.slate200,
    borderControl: '#7B8798',
    action: palette.blue600,
    actionPressed: palette.blue700,
    onAction: palette.white,
    actionTint: palette.blue100,
    focus: palette.blue600,
    success: palette.green700,
    successSurface: palette.green50,
    warning: palette.amber700,
    warningSurface: palette.amber100,
    danger: palette.red700,
    dangerSurface: palette.red50,
    onDanger: palette.white,
    scrim: 'rgba(15, 23, 42, 0.55)',
  },
  dark: {
    page: palette.navy950,
    surface: palette.navy900,
    surfaceMuted: palette.slate800,
    text: '#F8FAFC',
    textBody: palette.slate300,
    textMuted: palette.slate400,
    border: palette.slate800,
    borderControl: '#6B7A90',
    action: palette.blue200,
    actionPressed: '#C3D2FF',
    onAction: palette.navy950,
    actionTint: palette.blue900,
    focus: palette.blue300,
    success: palette.green400,
    successSurface: palette.green950,
    warning: palette.amber300,
    warningSurface: palette.amber950,
    danger: palette.red400,
    dangerSurface: palette.red950,
    onDanger: palette.navy950,
    scrim: 'rgba(0, 0, 0, 0.7)',
  },
  /** Calm, high-contrast theme for low vision and bright sunlight. */
  contrast: {
    page: palette.white,
    surface: palette.white,
    surfaceMuted: palette.slate100,
    text: palette.black,
    textBody: palette.navy900,
    textMuted: palette.slate800,
    border: palette.navy900,
    borderControl: palette.black,
    action: palette.blue700,
    actionPressed: '#14307F',
    onAction: palette.white,
    actionTint: palette.blue100,
    focus: palette.black,
    success: palette.green800,
    successSurface: palette.green50,
    warning: palette.amber900,
    warningSurface: palette.amber100,
    danger: palette.red900,
    dangerSurface: palette.red50,
    onDanger: palette.white,
    scrim: 'rgba(0, 0, 0, 0.75)',
  },
};

/**
 * Text and background pairs every theme must pass (WCAG 2.2 AA).
 * `min` is the required contrast ratio. Checked by the token tests.
 */
export const contrastPairs: ReadonlyArray<{ fg: keyof SemanticColors; bg: keyof SemanticColors; min: number }> = [
  { fg: 'text', bg: 'page', min: 4.5 },
  { fg: 'text', bg: 'surface', min: 4.5 },
  { fg: 'text', bg: 'surfaceMuted', min: 4.5 },
  { fg: 'textBody', bg: 'page', min: 4.5 },
  { fg: 'textBody', bg: 'surface', min: 4.5 },
  { fg: 'textMuted', bg: 'page', min: 4.5 },
  { fg: 'textMuted', bg: 'surface', min: 4.5 },
  { fg: 'textMuted', bg: 'surfaceMuted', min: 4.5 },
  { fg: 'action', bg: 'surface', min: 4.5 },
  { fg: 'action', bg: 'page', min: 4.5 },
  { fg: 'text', bg: 'actionTint', min: 4.5 },
  { fg: 'onAction', bg: 'action', min: 4.5 },
  { fg: 'onAction', bg: 'actionPressed', min: 4.5 },
  { fg: 'success', bg: 'surface', min: 4.5 },
  { fg: 'success', bg: 'successSurface', min: 4.5 },
  { fg: 'warning', bg: 'surface', min: 4.5 },
  { fg: 'warning', bg: 'warningSurface', min: 4.5 },
  { fg: 'danger', bg: 'surface', min: 4.5 },
  { fg: 'danger', bg: 'dangerSurface', min: 4.5 },
  { fg: 'onDanger', bg: 'danger', min: 4.5 },
  { fg: 'borderControl', bg: 'surface', min: 3 },
  { fg: 'focus', bg: 'surface', min: 3 },
  { fg: 'focus', bg: 'page', min: 3 },
];

/* ── Type ──────────────────────────────────────────────────────────────────── */

export const fontFamily = {
  sans: 'Inter',
  display: 'Inter Display',
} as const;

/**
 * Type scale in points (mobile) and CSS pixels (web). Body is 16 so older
 * users read it easily; 14 is the smallest size used anywhere.
 */
export const fontSize = {
  caption: 14,
  body: 16,
  bodyLarge: 18,
  lead: 20,
  title: 24,
  headline: 32,
  display: 40,
} as const;

/** Line height for each size (≥ 1.4 for body text, tighter for headings). */
export const lineHeight: Record<keyof typeof fontSize, number> = {
  caption: 20,
  body: 24,
  bodyLarge: 28,
  lead: 28,
  title: 32,
  headline: 40,
  display: 48,
};

export const fontWeight = {
  regular: '400',
  medium: '500',
  semibold: '600',
  bold: '700',
} as const;

/**
 * The user's text-size choice (Standard, Large, Largest). Multiplies every font
 * size; on mobile it stacks with the phone's own text setting.
 */
export const textScale = {
  standard: 1,
  large: 1.15,
  largest: 1.3,
} as const;

export type TextSize = keyof typeof textScale;

/* ── Space, shape, elevation, motion ──────────────────────────────────────── */

/** 4-point spacing scale. */
export const spacing = {
  xxs: 2,
  xs: 4,
  sm: 8,
  md: 12,
  lg: 16,
  xl: 24,
  xxl: 32,
  xxxl: 48,
} as const;

export const radius = {
  sm: 8,
  md: 12,
  lg: 16,
  pill: 999,
} as const;

/** Three elevation levels (web box-shadow values; mobile maps them to shadow/elevation). */
export const elevation = {
  low: '0 1px 2px rgba(15, 23, 42, 0.06), 0 1px 3px rgba(15, 23, 42, 0.08)',
  medium: '0 4px 12px rgba(15, 23, 42, 0.08), 0 2px 4px rgba(15, 23, 42, 0.06)',
  high: '0 16px 40px rgba(15, 23, 42, 0.14), 0 4px 12px rgba(15, 23, 42, 0.08)',
} as const;

/** Motion durations in ms. Use 0 when the user asks for reduced motion. */
export const motion = {
  fast: 150,
  normal: 250,
  easing: 'cubic-bezier(0.2, 0, 0, 1)',
} as const;

/** Focus ring width and offset in px. */
export const focusRing = { width: 3, offset: 2 } as const;

/** Minimum touch target in points (accessibility: large, easy taps). */
export const minTouchTarget = 48;

/* ── Backwards-compatible aliases (Sprint 1 names) ─────────────────────────── */

/** @deprecated Use `themes.light` (semantic) or `palette`. Kept for Sprint 1 code. */
export const colors = {
  navy: palette.navy900,
  ink: palette.navy900,
  blue: palette.blue600,
  blueStrong: palette.blue700,
  blueTint: palette.blue100,
  sky: palette.blue300,
  page: palette.slate50,
  surface: palette.white,
  border: palette.slate200,
  muted: palette.slate600,
  subtle: palette.slate500,
  success: palette.green700,
  warning: palette.amber700,
  danger: palette.red700,
} as const;

export type ColorToken = keyof typeof colors;

/* ── Contrast maths (shared by tests and tooling) ──────────────────────────── */

function channel(value: number): number {
  const c = value / 255;
  return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
}

/** Relative luminance of a `#RRGGBB` colour (WCAG 2.x). */
export function luminance(hex: string): number {
  const match = /^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i.exec(hex);
  if (!match) throw new Error(`Expected #RRGGBB, got ${hex}`);
  const [r, g, b] = [match[1], match[2], match[3]].map((h) => parseInt(h ?? '0', 16));
  return 0.2126 * channel(r ?? 0) + 0.7152 * channel(g ?? 0) + 0.0722 * channel(b ?? 0);
}

/** WCAG contrast ratio between two `#RRGGBB` colours (1 to 21). */
export function contrastRatio(a: string, b: string): number {
  const [hi, lo] = [luminance(a), luminance(b)].sort((x, y) => y - x);
  return ((hi ?? 0) + 0.05) / ((lo ?? 0) + 0.05);
}
