import { Text as RNText, type TextProps as RNTextProps } from 'react-native';
import { StyleSheet } from 'react-native-unistyles';

export type TextVariant = 'caption' | 'body' | 'bodyLarge' | 'lead' | 'title' | 'headline' | 'display';
export type TextTone = 'default' | 'body' | 'muted' | 'action' | 'success' | 'warning' | 'danger' | 'onAction';

export type TextProps = RNTextProps & {
  variant?: TextVariant;
  tone?: TextTone;
  weight?: 'regular' | 'medium' | 'semibold' | 'bold';
  /** Marks the text as a heading for screen readers. Titles and above default to true. */
  heading?: boolean;
};

/**
 * All app text. Sizes come from the type scale (16-point body, 14 minimum),
 * grow with the person's text-size choice and with the phone's own text
 * setting (capped at 2× so layouts stay usable).
 */
export function Text({ variant = 'body', tone = 'default', weight, heading, style, ...props }: TextProps) {
  // `default` is reserved by Unistyles; the base style already uses the default colour.
  styles.useVariants({ variant, tone: tone === 'default' ? undefined : tone });
  const isHeading = heading ?? ['title', 'headline', 'display'].includes(variant);

  return (
    <RNText
      accessibilityRole={isHeading ? 'header' : props.accessibilityRole}
      maxFontSizeMultiplier={2}
      style={[styles.text, weight ? styles.weight(weight) : undefined, style]}
      {...props}
    />
  );
}

const styles = StyleSheet.create((theme) => ({
  text: {
    color: theme.colors.text,
    variants: {
      variant: {
        caption: { fontSize: theme.font(theme.type.size.caption), lineHeight: theme.font(theme.type.lineHeight.caption) },
        body: { fontSize: theme.font(theme.type.size.body), lineHeight: theme.font(theme.type.lineHeight.body) },
        bodyLarge: { fontSize: theme.font(theme.type.size.bodyLarge), lineHeight: theme.font(theme.type.lineHeight.bodyLarge) },
        lead: { fontSize: theme.font(theme.type.size.lead), lineHeight: theme.font(theme.type.lineHeight.lead), fontWeight: '600' },
        title: { fontSize: theme.font(theme.type.size.title), lineHeight: theme.font(theme.type.lineHeight.title), fontWeight: '700' },
        headline: { fontSize: theme.font(theme.type.size.headline), lineHeight: theme.font(theme.type.lineHeight.headline), fontWeight: '700' },
        display: { fontSize: theme.font(theme.type.size.display), lineHeight: theme.font(theme.type.lineHeight.display), fontWeight: '800' },
      },
      tone: {
        body: { color: theme.colors.textBody },
        muted: { color: theme.colors.textMuted },
        action: { color: theme.colors.action },
        success: { color: theme.colors.success },
        warning: { color: theme.colors.warning },
        danger: { color: theme.colors.danger },
        onAction: { color: theme.colors.onAction },
      },
    },
  },
  weight: (weight: 'regular' | 'medium' | 'semibold' | 'bold') => ({ fontWeight: theme.type.weight[weight] }),
}));
