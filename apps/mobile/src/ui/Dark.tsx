import type { LucideIcon } from 'lucide-react-native';
import { ArrowRight } from 'lucide-react-native';
import { Pressable, View } from 'react-native';
import { StyleSheet } from 'react-native-unistyles';

import { Icon } from './Icon';
import { Text } from './Text';

/** The REOS mark and name at the top of the dark first-run screens. */
export function BrandMark() {
  return (
    <View accessible accessibilityRole="header" accessibilityLabel="REOS" style={styles.brand}>
      <View style={styles.mark}>
        <Text weight="bold" style={styles.onAction}>
          RE
        </Text>
      </View>
      <Text variant="title" heading={false} style={styles.light}>
        REOS
      </Text>
    </View>
  );
}

/**
 * A large choice card on a dark screen ("I want to become an agent"). The
 * main choice is filled blue; the rest are dark panels.
 */
export function PathCard({ icon, title, subtitle, primary = false, onPress }: { icon: LucideIcon; title: string; subtitle: string; primary?: boolean; onPress: () => void }) {
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={`${title}. ${subtitle}`}
      onPress={onPress}
      style={({ pressed }) => [styles.card, primary && styles.primary, pressed && styles.pressed]}
    >
      <View style={[styles.iconTile, primary && styles.iconTilePrimary]}>
        <Icon icon={icon} color={primary ? 'onAction' : 'heroText'} size={20} />
      </View>
      <View style={styles.text}>
        <Text weight="semibold" style={styles.light}>
          {title}
        </Text>
        <Text variant="caption" style={primary ? styles.light : styles.muted}>
          {subtitle}
        </Text>
      </View>
      <Icon icon={ArrowRight} color={primary ? 'onAction' : 'heroMuted'} size={20} />
    </Pressable>
  );
}

/** A small dark tile with a big value: "5 min · daily lessons". */
export function StatTile({ value, label }: { value: string; label: string }) {
  return (
    <View accessible accessibilityLabel={`${value} ${label}`} style={styles.stat}>
      <Text variant="title" style={styles.light}>
        {value}
      </Text>
      <Text variant="caption" style={styles.muted}>
        {label}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create((theme) => ({
  brand: { flexDirection: 'row', alignItems: 'center', gap: theme.space.md },
  mark: {
    width: theme.m.iconTile,
    height: theme.m.iconTile,
    borderRadius: theme.m.radius.tile,
    backgroundColor: theme.colors.action,
    alignItems: 'center',
    justifyContent: 'center',
  },
  onAction: { color: theme.colors.onAction },
  light: { color: theme.colors.heroText },
  muted: { color: theme.colors.heroMuted },
  card: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: theme.space.md,
    minHeight: theme.touch + theme.space.xl,
    paddingHorizontal: theme.space.lg,
    paddingVertical: theme.space.md,
    borderRadius: theme.m.radius.card,
    backgroundColor: theme.colors.navPanel,
  },
  primary: { backgroundColor: theme.colors.action },
  pressed: { opacity: 0.85 },
  iconTile: {
    width: theme.m.iconTile,
    height: theme.m.iconTile,
    borderRadius: theme.m.radius.tile,
    backgroundColor: theme.colors.heroBg,
    alignItems: 'center',
    justifyContent: 'center',
  },
  iconTilePrimary: { backgroundColor: theme.colors.actionPressed },
  text: { flex: 1, gap: theme.space.xxs },
  stat: { flex: 1, gap: theme.space.xxs, padding: theme.space.md, borderRadius: theme.m.radius.tile, backgroundColor: theme.colors.navPanel },
}));
