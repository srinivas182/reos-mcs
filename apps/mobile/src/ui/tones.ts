import type { SemanticColors } from '@reos/tokens';

/** Status tones used by chips, icon tiles and callouts (from the mobile journeys). */
export type Tone = 'success' | 'warning' | 'danger' | 'info' | 'neutral';

export const toneColors: Record<Tone, { bg: keyof SemanticColors; fg: keyof SemanticColors }> = {
  success: { bg: 'toneSuccessBg', fg: 'toneSuccessFg' },
  warning: { bg: 'toneWarningBg', fg: 'toneWarningFg' },
  danger: { bg: 'toneDangerBg', fg: 'toneDangerFg' },
  info: { bg: 'toneInfoBg', fg: 'toneInfoFg' },
  neutral: { bg: 'toneNeutralBg', fg: 'toneNeutralFg' },
};

/** A `#RRGGBB` colour at the given opacity, as `rgba()` (works on every renderer, unlike 8-digit hex). */
export function withAlpha(hex: string, alpha: number): string {
  const match = /^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i.exec(hex);
  if (!match) return hex;
  const [r, g, b] = [match[1], match[2], match[3]].map((part) => parseInt(part ?? '0', 16));
  return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}
