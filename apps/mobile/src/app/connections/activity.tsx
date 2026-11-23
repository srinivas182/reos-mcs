import { router } from "expo-router";
import { History } from "lucide-react-native";
import { View } from "react-native";
import { useTranslation } from "react-i18next";
import { StyleSheet } from "react-native-unistyles";

import { api } from "@/lib/api";
import { type Suggestion, useSyncActivity } from "@/lib/crm";
import { timeAgo } from "@/lib/time";
import {
  Button,
  Card,
  EmptyState,
  Eyebrow,
  Icon,
  Screen,
  Text,
  TitleBar,
  useUndoToast,
} from "@/ui";

/**
 * Sync activity (Connections and Sync journey, screen 23, B7.1): what
 * changed in your contacts and where, in plain words; when two sources
 * disagreed, the value that lost is in the sentence. Same-name contacts
 * are offered to merge (Undo splits them again).
 */
export default function SyncActivity() {
  const { t, i18n } = useTranslation();
  const toast = useUndoToast();
  const { activity, suggestions, reload } = useSyncActivity();

  const merge = async (s: Suggestion) => {
    const { response } = await api().POST("/contacts/{contact}/merge", {
      params: { path: { contact: s.contact.id } },
      body: { other: s.other.id },
    });

    if (!response.ok) return;
    await reload();
    toast({
      message: t("connections.activity.merged", { name: s.other.name }),
      onUndo: async () => {
        await api().POST("/contacts/{contact}/split", {
          params: { path: { contact: s.other.id } },
        });
        await reload();
      },
    });
  };

  const dismiss = async (s: Suggestion) => {
    await api().POST("/contacts/suggestions/{suggestion}/dismiss", {
      params: { path: { suggestion: s.id } },
    });
    await reload();
  };

  return (
    <Screen>
      <TitleBar
        title={t("connections.activity.title")}
        onBack={() => router.back()}
      />

      {suggestions.length > 0 ? (
        <View style={styles.group}>
          <Eyebrow>{t("connections.activity.duplicates")}</Eyebrow>
          {suggestions.map((s) => (
            <Card key={s.id}>
              <Text>
                {t("connections.activity.sameName", {
                  a: s.contact.name,
                  b: s.other.name,
                })}
              </Text>
              <View style={styles.row}>
                <Button
                  label={t("connections.activity.merge")}
                  onPress={() => void merge(s)}
                />
                <Button
                  label={t("connections.activity.notSame")}
                  variant="secondary"
                  onPress={() => void dismiss(s)}
                />
              </View>
            </Card>
          ))}
        </View>
      ) : null}

      {activity !== null && activity.length === 0 ? (
        <EmptyState
          icon={<Icon icon={History} color="action" size={28} />}
          title={t("connections.activity.empty")}
          description={t("connections.activity.emptyBody")}
        />
      ) : null}

      {(activity ?? []).map((item) => (
        <Card key={item.id}>
          <Text>{item.text}</Text>
          <Text tone="muted" variant="caption">
            {`${item.source_name} · ${timeAgo(item.occurred_at, i18n.language)}`}
          </Text>
        </Card>
      ))}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  group: { gap: theme.space.sm },
  row: { flexDirection: "row", flexWrap: "wrap", gap: theme.space.sm },
}));
