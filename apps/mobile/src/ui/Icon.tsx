import type { LucideIcon } from 'lucide-react-native';
import { useUnistyles } from 'react-native-unistyles';

import type { SemanticColors } from '@reos/tokens';

export type IconProps = {
  /** A Lucide icon (the same icon set as the web app and the journey designs). */
  icon: LucideIcon;
  /** A semantic colour name from the theme. */
  color?: keyof SemanticColors;
  size?: number;
  strokeWidth?: number;
  /** Fill the shape with the same colour (solid play button, active stars). */
  filled?: boolean;
};

/** Decorative icon in a theme colour. Hidden from screen readers; the label next to it does the talking. */
export function Icon({ icon: Glyph, color = 'text', size = 22, strokeWidth = 1.8, filled = false }: IconProps) {
  const { theme } = useUnistyles();

  return <Glyph color={theme.colors[color]} fill={filled ? theme.colors[color] : 'none'} size={size} strokeWidth={strokeWidth} aria-hidden />;
}
