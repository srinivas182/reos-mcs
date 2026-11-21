import { View } from 'react-native';
import { StyleSheet } from 'react-native-unistyles';

import { Button } from './Button';
import { Text } from './Text';

type SyncAlertProps = {
  /** Two letters for the tool's tile ("GC"). */
  initials: string;
  title: string;
  body: string;
  action: string;
  onPress: () => void;
};

/**
 * "When a sync breaks" (Connections and Sync journey, screen 26): a red card
 * at the top of Today saying what stopped, what may be out of date, and a
 * 30-second fix.
 */
export function SyncAlert({ initials, title, body, action, onPress }: SyncAlertProps) {
  return (
    <View accessibilityRole="summary" accessibilityLabel={title} style={styles.card}>
      <View style={styles.row}>
        <View style={styles.tile}>
          <Text weight="bold" tone="onAction" heading={false}>
            {initials}
          </Text>
        </View>
        <View style={styles.text}>
          <Text weight="bold" tone="danger" heading={false}>
            {title}
          </Text>
          <Text variant="caption" tone="danger">
            {body}
          </Text>
        </View>
      </View>
      <Button label={action} variant="destructive" fullWidth onPress={onPress} />
    </View>
  );
}

const styles = StyleSheet.create((theme) => ({
  card: {
    gap: theme.space.md,
    padding: theme.space.lg,
    borderRadius: theme.m.radius.card,
    borderWidth: 1,
    borderColor: theme.colors.toneDangerFg,
    backgroundColor: theme.colors.toneDangerBg,
  },
  row: { flexDirection: 'row', alignItems: 'center', gap: theme.space.md },
  tile: {
    width: theme.touch,
    height: theme.touch,
    borderRadius: theme.m.radius.button,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: theme.colors.action,
  },
  text: { flex: 1, gap: theme.space.xs },
}));
