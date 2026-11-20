/**
 * REOS design tokens (first cut, Sprint 1).
 *
 * One source of truth for colour, type and spacing on web and mobile.
 * Sprint 2.2 expands these from the journey designs; keep names stable,
 * because both apps import them directly.
 */

export const colors = {
    navy: '#0F172A',
    ink: '#0F172A',
    blue: '#2952CC',
    blueStrong: '#1E3FA3',
    blueTint: '#E8EEFC',
    sky: '#93C5FD',
    page: '#F6F7F9',
    surface: '#FFFFFF',
    border: '#E3E7EE',
    muted: '#475569',
    subtle: '#64748B',
    success: '#15803D',
    warning: '#B45309',
    danger: '#B42318',
} as const;

export const fontFamily = {
    sans: 'Inter',
} as const;

/** Type scale in points. Body text starts at 16 so older users read it easily. */
export const fontSize = {
    caption: 13,
    body: 16,
    bodyLarge: 18,
    title: 24,
    display: 32,
} as const;

/** 4-point spacing scale. */
export const spacing = {
    xs: 4,
    sm: 8,
    md: 12,
    lg: 16,
    xl: 24,
    xxl: 32,
} as const;

export const radius = {
    sm: 8,
    md: 12,
    lg: 16,
    pill: 999,
} as const;

/** Minimum touch target in points (accessibility: large, easy taps). */
export const minTouchTarget = 48;

export type ColorToken = keyof typeof colors;
