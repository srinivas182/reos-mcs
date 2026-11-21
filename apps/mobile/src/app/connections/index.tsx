import { router } from "expo-router";
import { Plug, Plus } from "lucide-react-native";
import { View } from "react-native";
import { useTranslation } from "react-i18next";
import { StyleSheet } from "react-native-unistyles";

import {
  type Connection,
  LIBRARY_GROUPS,
  syncState,
  toolInitials,
  useCatalogue,
  useConnections,
} from "@/lib/connections";
import { timeAgo } from "@/lib/time";
import {
  Button,
  Card,
  Chip,
  EmptyState,
  Eyebrow,
  Icon,
  IconButton,
  Screen,
  TitleBar,
  ToolRow,
} from "@/ui";

const stateTone = {
  upToDate: "success",
  syncing: "info",
  delayed: "warning",
  reconnect: "danger",
} as const;

/**
 * Connections: one place (Connections and Sync journey, screen 12). Every
 * connected tool grouped by type, with its status and last sync. "+" adds one.
 * Also where `reos://connections` lands after a tool's sign-in.
 */
export default function ConnectionsHub() {
  const { t, i18n } = useTranslation();
  const { connections } = useConnections();
  const { items } = useCatalogue();
  const groupOf = (connection: Connection) =>
    items?.find((item) => item.id === connection.integration)?.group ?? "more";
  const add = () => router.push("/connections/add");

  return (
    <Screen>
      <View style={styles.titleRow}>
        <View style={styles.flex}>
          <TitleBar
            title={t("connections.hub.title")}
            onBack={() => router.back()}
          />
        </View>
        <IconButton
          icon={Plus}
          label={t("connections.hub.add")}
          onPress={add}
        />
      </View>

      {connections !== null && connections.length === 0 ? (
        <EmptyState
          icon={<Icon icon={Plug} color="action" size={28} />}
          title={t("connections.hub.empty")}
          description={t("connections.hub.emptyBody")}
          action={<Button label={t("connections.hub.add")} onPress={add} />}
        />
      ) : null}

      {LIBRARY_GROUPS.map((group) => {
        const inGroup = (connections ?? []).filter(
          (connection) => groupOf(connection) === group,
        );

        if (inGroup.length === 0) return null;

        return (
          <View key={group} style={styles.group}>
            <Eyebrow>
              {t("connections.hub.groupCount", {
                group: t(`connections.hub.groups.${group}`),
                count: inGroup.length,
              })}
            </Eyebrow>
            {inGroup.map((connection) => {
              const state = syncState(connection);

              return (
                <Card key={connection.id}>
                  <ToolRow
                    initials={toolInitials(connection.integration_name)}
                    name={connection.integration_name}
                    detail={connection.account ?? undefined}
                    oneLine="detail"
                    meta={
                      connection.last_synced_at
                        ? t("connections.hub.ago", {
                            ago: timeAgo(
                              connection.last_synced_at,
                              i18n.language,
                            ).toLowerCase(),
                          })
                        : t("connections.hub.never")
                    }
                    trailing={
                      <Chip
                        label={t(`connections.state.${state}`)}
                        tone={stateTone[state]}
                      />
                    }
                    onPress={() =>
                      state === "reconnect"
                        ? router.push({
                            pathname: "/reconnect/[id]",
                            params: { id: String(connection.id) },
                          })
                        : router.push({
                            pathname: "/connections/[id]",
                            params: { id: String(connection.id) },
                          })
                    }
                  />
                </Card>
              );
            })}
          </View>
        );
      })}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  titleRow: { flexDirection: "row", alignItems: "center", gap: theme.space.sm },
  flex: { flex: 1 },
  group: { gap: theme.space.sm },
}));
