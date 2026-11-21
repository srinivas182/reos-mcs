import { router, useLocalSearchParams } from "expo-router";
import { Check } from "lucide-react-native";
import { useState } from "react";
import { View } from "react-native";
import { useTranslation } from "react-i18next";
import { StyleSheet } from "react-native-unistyles";

import { api } from "@/lib/api";
import { type Connection, useConnections } from "@/lib/connections";
import {
  Button,
  Callout,
  Card,
  Icon,
  PillGroup,
  Screen,
  Text,
  TitleBar,
  useUndoToast,
} from "@/ui";

type History = Connection["history"];

/**
 * What to sync (Connections and Sync journey, screen 7): what syncs, which way
 * and how far back. Most people never open this. Calendar events are read
 * only until two-way calendars arrive (B4.1), so only the history changes.
 */
export default function SyncSettings() {
  const { t } = useTranslation();
  const toast = useUndoToast();
  const { id } = useLocalSearchParams<{ id: string }>();
  const { connections } = useConnections();
  const connection = connections?.find((c) => String(c.id) === id);
  const [history, setHistory] = useState<History | null>(null);
  const [busy, setBusy] = useState(false);

  if (!connection) {
    return <Screen>{null}</Screen>;
  }

  const chosen = history ?? connection.history;

  const save = async () => {
    setBusy(true);
    const { response } = await api().PATCH("/connections/{connection}", {
      params: { path: { connection: connection.id } },
      body: { history: chosen },
    });
    setBusy(false);

    if (response.ok) {
      toast({ message: t("connections.settings.saved") });
      router.back();
    }
  };

  return (
    <Screen
      footer={
        <View style={styles.footer}>
          <Button
            label={t("connections.settings.save")}
            loading={busy}
            fullWidth
            onPress={() => void save()}
          />
        </View>
      }
    >
      <TitleBar
        title={t("connections.settings.title")}
        onBack={() => router.back()}
      />
      {connection.account ? (
        <Text tone="muted">
          {t("connections.settings.signedInAs", {
            tool: connection.integration_name,
            account: connection.account,
          })}
        </Text>
      ) : null}
      <Card>
        {connection.streams.map((stream) => (
          <View key={stream.key} style={styles.row}>
            <View style={styles.flex}>
              <Text weight="semibold">
                {t(`connections.connected.streams.${stream.key}`)}
              </Text>
              <Text variant="caption" tone="muted">
                {t(`connections.connected.directions.${stream.direction}`)}
              </Text>
            </View>
            <Icon icon={Check} color="success" size={20} />
          </View>
        ))}
      </Card>
      <Text variant="bodyLarge" weight="semibold">
        {t("connections.settings.howFar")}
      </Text>
      <PillGroup
        label={t("connections.settings.howFar")}
        value={chosen}
        options={(["3m", "12m", "all"] as const).map((value) => ({
          value,
          label: t(`connections.connected.historyValues.${value}`),
        }))}
        onChange={setHistory}
      />
      <Callout tone="info" title={t("connections.settings.privacy")} />
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  footer: { paddingHorizontal: theme.m.gutter, paddingBottom: theme.space.xl },
  row: {
    flexDirection: "row",
    alignItems: "center",
    gap: theme.space.sm,
    minHeight: theme.touch,
  },
  flex: { flex: 1 },
}));
