import { router, useLocalSearchParams } from "expo-router";
import { useState } from "react";
import { View } from "react-native";
import { useTranslation } from "react-i18next";
import { StyleSheet } from "react-native-unistyles";

import { api } from "@/lib/api";
import { syncState, toolInitials, useConnections } from "@/lib/connections";
import { timeAgo } from "@/lib/time";
import {
  Button,
  Card,
  Chip,
  MenuGroup,
  MenuRow,
  Screen,
  Sheet,
  Text,
  TitleBar,
  ToolRow,
  useUndoToast,
} from "@/ui";
import { RefreshCw, SlidersHorizontal } from "lucide-react-native";

const stateTone = {
  upToDate: "success",
  syncing: "info",
  delayed: "warning",
  reconnect: "danger",
} as const;

/**
 * One connected tool (Connections and Sync journey, screens 15–16): what is
 * synced and when, Sync now, What to sync, and Disconnect. Disconnecting
 * removes what REOS copied; nothing is deleted in the tool itself.
 */
export default function ConnectionDetail() {
  const { t, i18n } = useTranslation();
  const toast = useUndoToast();
  const { id } = useLocalSearchParams<{ id: string }>();
  const { connections, reload } = useConnections();
  const connection = connections?.find((c) => String(c.id) === id);
  const [confirming, setConfirming] = useState(false);
  const [busy, setBusy] = useState(false);

  if (!connection) {
    return <Screen>{null}</Screen>;
  }

  const tool = connection.integration_name;
  const state = syncState(connection);
  const records = connection.streams.reduce(
    (sum, stream) => sum + stream.records,
    0,
  );

  const syncNow = async () => {
    const { response } = await api().POST("/connections/{connection}/sync", {
      params: { path: { connection: connection.id } },
    });
    if (response.ok) toast({ message: t("connections.detail.syncQueued") });
  };

  const disconnect = async () => {
    setBusy(true);
    const { response } = await api().DELETE("/connections/{connection}", {
      params: { path: { connection: connection.id } },
    });
    setBusy(false);
    setConfirming(false);

    if (response.ok) {
      await reload();
      toast({ message: t("connections.detail.disconnected", { tool }) });
      router.back();
    }
  };

  return (
    <Screen>
      <TitleBar title={tool} onBack={() => router.back()} />
      <Card>
        <ToolRow
          initials={toolInitials(tool)}
          name={connection.account ?? tool}
          oneLine="name"
          detail={
            connection.last_synced_at
              ? t("connections.hub.ago", {
                  ago: timeAgo(
                    connection.last_synced_at,
                    i18n.language,
                  ).toLowerCase(),
                })
              : t("connections.hub.never")
          }
          meta={t("connections.detail.records", { count: records })}
          trailing={
            <Chip
              label={t(`connections.state.${state}`)}
              tone={stateTone[state]}
            />
          }
        />
        {connection.demo ? (
          <Chip label={t("connections.detail.demo")} tone="neutral" />
        ) : null}
      </Card>

      <MenuGroup>
        <MenuRow
          icon={RefreshCw}
          title={t("connections.detail.syncNow")}
          onPress={() => void syncNow()}
        />
        <MenuRow
          icon={SlidersHorizontal}
          title={t("connections.detail.whatToSync")}
          onPress={() =>
            router.push({
              pathname: "/connections/[id]/settings",
              params: { id: String(connection.id) },
            })
          }
        />
      </MenuGroup>

      <Button
        label={t("connections.detail.disconnect")}
        variant="ghost"
        onPress={() => setConfirming(true)}
      />

      <Sheet
        open={confirming}
        onClose={() => setConfirming(false)}
        title={t("connections.detail.confirmTitle", { tool })}
      >
        <Text>{t("connections.detail.confirmBody", { tool })}</Text>
        <View style={styles.actions}>
          <Button
            label={t("connections.detail.confirm")}
            variant="destructive"
            loading={busy}
            fullWidth
            onPress={() => void disconnect()}
          />
          <Button
            label={t("connections.detail.keep")}
            variant="secondary"
            fullWidth
            onPress={() => setConfirming(false)}
          />
        </View>
      </Sheet>
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  actions: { gap: theme.space.sm, paddingTop: theme.space.sm },
}));
