import { Pressable, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { roleTabs, type AppRole, type TabKey } from '@/navigation/roleTabs';

import { Icon } from './Icon';
import { Text } from './Text';

type TabBarProps = {
  role: AppRole;
  active: TabKey;
  onSelect: (key: TabKey) => void;
  /** Unread or pending counts per tab, announced with the tab name. */
  badges?: Partial<Record<TabKey, number>>;
};

/**
 * The bottom tab bar from the journeys, with each role's own tabs. The active
 * tab gets a tinted pill behind its icon. Screen readers hear "Today, tab, 1 of 4, selected".
 */
export function TabBar({ role, active, onSelect, badges = {} }: TabBarProps) {
  const { t } = useTranslation();

  return (
    <View accessibilityRole="tablist" style={styles.bar}>
      {roleTabs[role].map(({ key, icon }) => {
        const selected = key === active;
        const count = badges[key] ?? 0;
        const label = t(`tabs.${key}`);

        return (
          <Pressable
            key={key}
            accessibilityRole="tab"
            accessibilityLabel={count > 0 ? t('tabs.withCount', { label, count }) : label}
            accessibilityState={{ selected }}
            onPress={() => onSelect(key)}
            style={styles.tab}
          >
            <View style={[styles.pill, selected && styles.pillOn]}>
              <Icon icon={icon} color={selected ? 'toneInfoFg' : 'textMuted'} size={22} />
              {count > 0 ? (
                <View style={styles.badge}>
                  <Text variant="caption" weight="bold" style={styles.badgeText} maxFontSizeMultiplier={1.3}>
                    {count > 99 ? '99+' : count}
                  </Text>
                </View>
              ) : null}
            </View>
            <Text variant="caption" weight="semibold" numberOfLines={1} maxFontSizeMultiplier={1.4} tone={selected ? undefined : 'muted'} style={selected ? styles.labelOn : undefined}>
              {label}
            </Text>
          </Pressable>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create((theme, rt) => ({
  bar: {
    flexDirection: 'row',
    paddingTop: theme.space.sm,
    paddingHorizontal: theme.space.sm,
    paddingBottom: Math.max(rt.insets.bottom, theme.space.sm),
    backgroundColor: theme.colors.surface,
    borderTopWidth: 1,
    borderTopColor: theme.colors.border,
  },
  tab: { flex: 1, alignItems: 'center', gap: 4, minHeight: theme.touch + 8, paddingVertical: 4 },
  pill: { width: 52, height: 32, borderRadius: theme.m.radius.pill, alignItems: 'center', justifyContent: 'center' },
  pillOn: { backgroundColor: theme.colors.toneInfoBg },
  labelOn: { color: theme.colors.toneInfoFg },
  badge: {
    position: 'absolute',
    top: -4,
    right: 4,
    minWidth: 20,
    height: 20,
    paddingHorizontal: 4,
    borderRadius: theme.m.radius.pill,
    backgroundColor: theme.colors.danger,
    alignItems: 'center',
    justifyContent: 'center',
  },
  badgeText: { color: theme.colors.onDanger, fontSize: 12, lineHeight: 16 },
}));
