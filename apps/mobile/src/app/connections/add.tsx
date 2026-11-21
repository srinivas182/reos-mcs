import { router } from "expo-router";
import { useMemo, useState } from "react";
import { View } from "react-native";
import { useTranslation } from "react-i18next";
import { StyleSheet } from "react-native-unistyles";

import { api } from "@/lib/api";
import {
  type CatalogueItem,
  LIBRARY_GROUPS,
  type LibraryGroup,
  setInterest,
  toolInitials,
  useCatalogue,
} from "@/lib/connections";
import {
  Button,
  Card,
  Chip,
  PillGroup,
  Screen,
  Sheet,
  Text,
  TextField,
  TitleBar,
  ToolRow,
  useUndoToast,
} from "@/ui";

/**
 * Add a connection (Connections and Sync journey, screens 2–4): search, the
 * tool types as tabs, and each tool's action from the super admin's
 * catalogue: Connect, On, Notify me, Join beta or "Temporarily unavailable".
 * "My tool isn't listed" asks for one.
 */
export default function AddConnection() {
  const { t, i18n } = useTranslation();
  const toast = useUndoToast();
  const { items, reload } = useCatalogue();
  const [group, setGroup] = useState<LibraryGroup>("crm");
  const [query, setQuery] = useState("");
  const [requesting, setRequesting] = useState(false);
  const search = query.trim().toLowerCase();

  const shown = useMemo(
    () =>
      (items ?? []).filter((item) =>
        search === ""
          ? item.group === group
          : item.name.toLowerCase().includes(search) ||
            item.purpose.toLowerCase().includes(search),
      ),
    [items, group, search],
  );

  const follow = async (item: CatalogueItem) => {
    if (!(await setInterest(item.id, true))) return;
    await reload();
    toast({
      message:
        item.action === "join_beta"
          ? t("connections.library.betaToast", { tool: item.name })
          : t("connections.library.notifyToast", { tool: item.name }),
      onUndo: async () => {
        await setInterest(item.id, false);
        await reload();
      },
      undoneMessage: t("connections.library.undone"),
    });
  };

  const trailing = (item: CatalogueItem) => {
    if (item.connected > 0)
      return <Chip label={t("connections.library.on")} tone="success" />;
    if (item.action === "connect") {
      return (
        <Chip
          label={t("connections.library.connect")}
          tone="info"
          onPress={() =>
            router.push({
              pathname: "/connections/consent/[tool]",
              params: { tool: item.id },
            })
          }
          accessibilityLabel={`${t("connections.library.connect")} ${item.name}`}
        />
      );
    }
    if (item.action === "notify" || item.action === "join_beta") {
      if (item.interested)
        return (
          <Chip
            label={
              item.action === "notify"
                ? t("connections.library.notified")
                : t("connections.library.asked")
            }
            tone="neutral"
          />
        );

      return (
        <Chip
          label={
            item.action === "notify"
              ? t("connections.library.notify")
              : t("connections.library.joinBeta")
          }
          tone="neutral"
          onPress={() => void follow(item)}
          accessibilityLabel={`${item.action === "notify" ? t("connections.library.notify") : t("connections.library.joinBeta")} · ${item.name}`}
        />
      );
    }
    if (item.status === "retired" && item.retires_on) {
      return (
        <Chip
          label={t("connections.library.retiring", {
            date: new Intl.DateTimeFormat(i18n.language, {
              month: "short",
              day: "numeric",
            }).format(new Date(`${item.retires_on}T12:00:00`)),
          })}
          tone="warning"
        />
      );
    }

    return <Chip label={t("connections.library.unavailable")} tone="warning" />;
  };

  return (
    <Screen>
      <TitleBar
        title={t("connections.library.title")}
        onBack={() => router.back()}
      />
      <TextField
        label={t("connections.library.search", { count: items?.length ?? 0 })}
        value={query}
        onChangeText={setQuery}
        autoCorrect={false}
      />
      {search === "" ? (
        <PillGroup
          label={t("connections.library.tabs")}
          value={group}
          options={LIBRARY_GROUPS.map((value) => ({
            value,
            label: t(`connections.hub.groups.${value}`),
          }))}
          onChange={setGroup}
        />
      ) : null}

      {shown.length > 0 ? (
        <Card>
          {shown.map((item) => (
            <ToolRow
              key={item.id}
              initials={toolInitials(item.name)}
              name={item.name}
              detail={
                item.accounts === "tenant"
                  ? `${item.purpose} · ${t("connections.library.broker")}`
                  : item.purpose
              }
              trailing={trailing(item)}
            />
          ))}
        </Card>
      ) : search !== "" ? (
        <Text tone="muted">
          {t("connections.library.noResults", { search: query })}
        </Text>
      ) : null}

      <Button
        label={t("connections.library.notListed")}
        variant="ghost"
        onPress={() => setRequesting(true)}
      />
      <RequestSheet open={requesting} onClose={() => setRequesting(false)} />
    </Screen>
  );
}

/** "My tool isn't listed". */
function RequestSheet({
  open,
  onClose,
}: {
  open: boolean;
  onClose: () => void;
}) {
  const { t } = useTranslation();
  const toast = useUndoToast();
  const [name, setName] = useState("");
  const [note, setNote] = useState("");
  const [busy, setBusy] = useState(false);

  const send = async () => {
    setBusy(true);
    const { response } = await api().POST("/tool-requests", {
      body: { name, note: note || null },
    });
    setBusy(false);

    if (response.ok) {
      setName("");
      setNote("");
      onClose();
      toast({ message: t("connections.request.sent") });
    }
  };

  return (
    <Sheet open={open} onClose={onClose} title={t("connections.request.title")}>
      <Text tone="muted">{t("connections.request.intro")}</Text>
      <TextField
        label={t("connections.request.name")}
        value={name}
        onChangeText={setName}
      />
      <TextField
        label={t("connections.request.note")}
        value={note}
        onChangeText={setNote}
      />
      <View style={styles.send}>
        <Button
          label={t("connections.request.send")}
          loading={busy}
          disabled={name.trim().length < 2}
          fullWidth
          onPress={() => void send()}
        />
      </View>
    </Sheet>
  );
}

const styles = StyleSheet.create((theme) => ({
  send: { paddingTop: theme.space.sm },
}));
