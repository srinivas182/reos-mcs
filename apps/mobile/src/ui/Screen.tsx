import type { ReactNode } from 'react';
import { ScrollView, View } from 'react-native';
import { StyleSheet } from 'react-native-unistyles';

type ScreenProps = {
  children: ReactNode;
  /** Fixed at the bottom above the tab bar: the Ask bar, or the screen's main button. */
  footer?: ReactNode;
  /** Shown under the footer (the role's TabBar) until Sprint 3 wires real tab navigation. */
  tabBar?: ReactNode;
  /** Dark welcome screens (first-run, licence celebration). */
  dark?: boolean;
};

/** A journey screen: page background, 20-pt gutters, safe areas, scrolling content, fixed footer. */
export function Screen({ children, footer, tabBar, dark = false }: ScreenProps) {
  styles.useVariants({ dark });

  return (
    <View style={styles.root}>
      <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
        {children}
      </ScrollView>
      {footer ? <View style={styles.footer}>{footer}</View> : null}
      {tabBar}
    </View>
  );
}

const styles = StyleSheet.create((theme, rt) => ({
  root: {
    flex: 1,
    backgroundColor: theme.colors.page,
    variants: { dark: { true: { backgroundColor: theme.colors.heroBg }, false: {} } },
  },
  content: {
    gap: theme.space.md,
    paddingTop: rt.insets.top + theme.space.lg,
    paddingBottom: theme.space.xl,
    paddingHorizontal: theme.m.gutter,
  },
  footer: { paddingTop: theme.space.xs },
}));
