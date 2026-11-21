import type { ReactNode } from "react";
import { Pressable, View } from "react-native";
import { StyleSheet } from "react-native-unistyles";

import { Text } from "./Text";

type ToolRowProps = {
  /** Two letters for the tile ("FU", "GC"). */
  initials: string;
  name: string;
  detail?: string;
  /** A second, quieter line ("Synced 2 minutes ago"). */
  meta?: string;
  /** A chip or small button on the right. */
  trailing?: ReactNode;
  onPress?: () => void;
  /** Keep the name or the detail on one line, shortened in the middle (email addresses). */
  oneLine?: "name" | "detail";
};

/**
 * One tool in a list (Connections and Sync journey, screens 2–4 and 12): its
 * tile, name, what it does or which account, and a status or action.
 */
export function ToolRow({
  initials,
  name,
  detail,
  meta,
  trailing,
  onPress,
  oneLine,
}: ToolRowProps) {
  const body = (
    <>
      <View style={styles.tile}>
        <Text variant="caption" weight="bold" tone="onAction" heading={false}>
          {initials}
        </Text>
      </View>
      <View style={styles.text}>
        <Text
          weight="semibold"
          heading={false}
          {...(oneLine === "name" ? single : {})}
        >
          {name}
        </Text>
        {detail ? (
          <Text
            variant="caption"
            tone="muted"
            {...(oneLine === "detail" ? single : {})}
          >
            {detail}
          </Text>
        ) : null}
        {meta ? (
          <Text variant="caption" tone="muted">
            {meta}
          </Text>
        ) : null}
      </View>
      {trailing}
    </>
  );

  if (onPress) {
    return (
      <Pressable
        accessibilityRole="button"
        accessibilityLabel={[name, detail].filter(Boolean).join(", ")}
        onPress={onPress}
        style={({ pressed }) => [styles.row, pressed && styles.pressed]}
      >
        {body}
      </Pressable>
    );
  }

  return <View style={styles.row}>{body}</View>;
}

const single = { numberOfLines: 1, ellipsizeMode: "middle" } as const;

const styles = StyleSheet.create((theme) => ({
  row: {
    flexDirection: "row",
    alignItems: "center",
    gap: theme.space.md,
    minHeight: theme.touch + theme.space.sm,
    paddingVertical: theme.space.sm,
  },
  pressed: { opacity: 0.7 },
  tile: {
    width: theme.touch - theme.space.sm,
    height: theme.touch - theme.space.sm,
    borderRadius: theme.m.radius.button,
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: theme.colors.action,
  },
  text: { flex: 1, gap: 2 },
}));
