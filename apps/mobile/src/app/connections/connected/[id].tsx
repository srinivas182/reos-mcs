import { router, useLocalSearchParams } from "expo-router";
import { Check } from "lucide-react-native";
import { View } from "react-native";
import { useTranslation } from "react-i18next";
import { StyleSheet } from "react-native-unistyles";

import { toolInitials, useConnections } from "@/lib/connections";
import { Button, Card, Icon, Screen, Text } from "@/ui";

/**
 * Connected with smart defaults (Connections and Sync journey, screen 6): no
 * setup form. What syncs, which way and how far back are already chosen;
 * "Change settings" is there for the few who care. Syncing has started.
 */
export default function ConnectedScreen() {
  const { t } = useTranslation();
  const { id } = useLocalSearchParams<{ id: string }>();
  const { connections } = useConnections();
  const connection = connections?.find((c) => String(c.id) === id);

  if (!connection) {
    return <Screen>{null}</Screen>;
  }

  const tool = connection.integration_name;
  const rows = [
    ...connection.streams.map((stream) => ({
      key: stream.key,
      label: t(`connections.connected.streams.${stream.key}`),
      value: t(`connections.connected.directions.${stream.direction}`),
    })),
    {
      key: "history",
      label: t("connections.connected.history"),
      value: t(`connections.connected.historyValues.${connection.history}`),
    },
  ];

  return (
    <Screen
      footer={
        <View style={styles.footer}>
          <Button
            label={t("connections.connected.done")}
            fullWidth
            onPress={() => router.replace("/connections")}
          />
        </View>
      }
    >
      <View style={styles.tile}>
        <Text variant="title" tone="onAction" heading={false}>
          {toolInitials(tool)}
        </Text>
      </View>
      <Text variant="title" style={styles.center}>
        {t("connections.connected.title", { tool })}
      </Text>
      <Text tone="muted" style={styles.center}>
        {t("connections.connected.body")}
      </Text>
      <Card>
        {rows.map((row) => (
          <View key={row.key} style={styles.row}>
            <Text style={styles.flex}>{row.label}</Text>
            <Text variant="caption" tone="muted">
              {row.value}
            </Text>
            <Icon icon={Check} color="success" size={20} />
          </View>
        ))}
      </Card>
      <Button
        label={t("connections.connected.change")}
        variant="ghost"
        onPress={() =>
          router.push({
            pathname: "/connections/[id]/settings",
            params: { id: String(connection.id) },
          })
        }
      />
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  footer: { paddingHorizontal: theme.m.gutter, paddingBottom: theme.space.xl },
  tile: {
    alignSelf: "center",
    width: 64,
    height: 64,
    borderRadius: theme.m.radius.card,
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: theme.colors.action,
    marginTop: theme.space.lg,
  },
  center: { textAlign: "center" },
  row: {
    flexDirection: "row",
    alignItems: "center",
    gap: theme.space.sm,
    minHeight: theme.touch,
  },
  flex: { flex: 1 },
}));
