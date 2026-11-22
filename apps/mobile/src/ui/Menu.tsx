import { initialsOf } from '@/lib/personName';
import type { LucideIcon } from 'lucide-react-native';
import { ArrowRight, Check, ChevronLeft } from 'lucide-react-native';
import type { ReactNode } from 'react';
import { Children, Fragment } from 'react';
import { Pressable, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { Icon } from './Icon';
import { Text } from './Text';

/** Initials from a name: "James Miller" → "JM" (titles skipped). */
export { initialsOf };

/**
 * One white card of rows divided by hairlines, as on Me (journey screen 16)
 * and the settings screens.
 */
export function MenuGroup({ children }: { children: ReactNode }) {
  const rows = Children.toArray(children).filter(Boolean);

  return (
    <View style={styles.group}>
      {rows.map((row, index) => (
        <Fragment key={index}>
          {index > 0 ? <View style={styles.divider} /> : null}
          {row}
        </Fragment>
      ))}
    </View>
  );
}

type MenuRowProps = {
  icon?: LucideIcon;
  title: string;
  /** Plain text on the right: "9 of 18 h", "Sponsored". */
  value?: string;
  /** A chip on the right instead of a value: "Launch", "1 to fix". */
  chip?: ReactNode;
  /** Red title, no arrow (Delete account). */
  destructive?: boolean;
  onPress?: () => void;
};

/** A row in a MenuGroup: line icon, title, value or chip, then an arrow. */
export function MenuRow({ icon, title, value, chip, destructive = false, onPress }: MenuRowProps) {
  return (
    <Pressable
      accessibilityRole={onPress ? 'button' : undefined}
      accessibilityLabel={value ? `${title}, ${value}` : title}
      disabled={!onPress}
      onPress={onPress}
      style={({ pressed }) => [styles.row, pressed && styles.pressed]}
    >
      {icon ? <Icon icon={icon} color={destructive ? 'danger' : 'text'} /> : null}
      <Text variant="bodyLarge" tone={destructive ? 'danger' : 'default'} style={styles.rowTitle}>
        {title}
      </Text>
      {chip}
      {value ? <Text tone="muted">{value}</Text> : null}
      {onPress && !destructive ? <Icon icon={ArrowRight} color="textMuted" size={20} /> : null}
    </Pressable>
  );
}

/** A card with one row on its own, such as "Switch role · Instructor". */
export function MenuCard({ icon, title, onPress }: { icon: LucideIcon; title: string; onPress: () => void }) {
  return (
    <Pressable accessibilityRole="button" accessibilityLabel={title} onPress={onPress} style={({ pressed }) => [styles.group, styles.row, pressed && styles.pressed]}>
      <Icon icon={icon} color="action" />
      <Text variant="bodyLarge" weight="semibold" style={styles.rowTitle}>
        {title}
      </Text>
      <Icon icon={ArrowRight} color="textMuted" size={20} />
    </Pressable>
  );
}

/**
 * The top of Me: large initials, the name, and a line under it. Part of
 * that line (the organisation) can be tapped to switch organisation.
 */
export function ProfileHeader({ name, detail, link, onLinkPress }: { name: string; detail: string; link?: string; onLinkPress?: () => void }) {
  return (
    <View style={styles.profile}>
      <View accessibilityElementsHidden importantForAccessibility="no-hide-descendants" style={styles.bigAvatar}>
        <Text variant="title" style={styles.initials}>
          {initialsOf(name)}
        </Text>
      </View>
      <View style={styles.profileText}>
        <Text variant="title" heading>
          {name}
        </Text>
        <View style={styles.detail}>
          <Text tone="muted">{link ? `${detail} · ` : detail}</Text>
          {link ? (
            <Pressable accessibilityRole="button" accessibilityLabel={link} disabled={!onLinkPress} onPress={onLinkPress} hitSlop={12}>
              <Text tone="action" weight="semibold">
                {link}
              </Text>
            </Pressable>
          ) : null}
        </View>
      </View>
    </View>
  );
}

/**
 * A choice in a sheet (Your organisations, Switch role): a dark tile with
 * initials, title and detail, and a tick on the one in use.
 */
export function ChoiceRow({
  title,
  detail,
  tile,
  selected,
  onPress,
}: {
  title: string;
  detail?: string;
  /** Whose initials go on the tile (default: the title). */
  tile?: string;
  selected: boolean;
  onPress: () => void;
}) {
  return (
    <Pressable
      accessibilityRole="radio"
      accessibilityState={{ checked: selected }}
      accessibilityLabel={detail ? `${title}. ${detail}` : title}
      onPress={onPress}
      style={({ pressed }) => [styles.choice, pressed && styles.pressed]}
    >
      <View style={styles.choiceTile}>
        <Text weight="bold" style={styles.choiceInitials}>
          {initialsOf(tile ?? title)}
        </Text>
      </View>
      <View style={styles.rowTitle}>
        <Text variant="bodyLarge" weight="semibold">
          {title}
        </Text>
        {detail ? <Text tone="muted">{detail}</Text> : null}
      </View>
      {selected ? <Icon icon={Check} color="action" /> : null}
    </Pressable>
  );
}

/** Back arrow and the screen's title on one line (journey settings screens), with an optional action on the right (the round + on My licences). */
export function TitleBar({ title, onBack, action }: { title: string; onBack: () => void; action?: ReactNode }) {
  const { t } = useTranslation();

  return (
    <View style={styles.titleBar}>
      <Pressable accessibilityRole="button" accessibilityLabel={t('common.back')} onPress={onBack} hitSlop={8} style={styles.back}>
        <Icon icon={ChevronLeft} />
      </Pressable>
      <Text variant="title" style={styles.titleText}>
        {title}
      </Text>
      {action}
    </View>
  );
}

/** Small blue capitals above a group: "LANGUAGE", "TEXT SIZE". */
export function Eyebrow({ children }: { children: string }) {
  return (
    <Text variant="caption" weight="bold" tone="action" style={styles.eyebrow}>
      {children.toUpperCase()}
    </Text>
  );
}

const styles = StyleSheet.create((theme) => ({
  group: {
    backgroundColor: theme.colors.surface,
    borderRadius: theme.m.radius.card,
    borderWidth: 1,
    borderColor: theme.colors.border,
    paddingHorizontal: theme.space.lg,
  },
  divider: { height: 1, backgroundColor: theme.colors.border },
  row: { flexDirection: 'row', alignItems: 'center', gap: theme.space.md, minHeight: theme.touch + theme.space.sm, paddingVertical: theme.space.sm },
  rowTitle: { flex: 1 },
  pressed: { opacity: 0.85 },
  profile: { flexDirection: 'row', alignItems: 'center', gap: theme.space.lg },
  bigAvatar: {
    width: theme.m.avatarLarge,
    height: theme.m.avatarLarge,
    borderRadius: theme.m.radius.pill,
    backgroundColor: theme.colors.toneInfoBg,
    alignItems: 'center',
    justifyContent: 'center',
  },
  initials: { color: theme.colors.toneInfoFg },
  profileText: { flex: 1, gap: theme.space.xs },
  detail: { flexDirection: 'row', flexWrap: 'wrap', alignItems: 'center' },
  choice: { flexDirection: 'row', alignItems: 'center', gap: theme.space.md, minHeight: theme.touch + theme.space.md },
  choiceTile: {
    width: theme.m.iconTile,
    height: theme.m.iconTile,
    borderRadius: theme.m.radius.tile,
    backgroundColor: theme.colors.heroBg,
    alignItems: 'center',
    justifyContent: 'center',
  },
  choiceInitials: { color: theme.colors.heroText },
  titleBar: { flexDirection: 'row', alignItems: 'center', gap: theme.space.sm },
  back: { width: theme.touch, height: theme.touch, alignItems: 'center', justifyContent: 'center', marginLeft: -theme.space.md },
  titleText: { flex: 1, letterSpacing: -0.5 },
  eyebrow: { letterSpacing: 1 },
}));
