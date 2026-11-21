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
  Eyebrow,
  Icon,
  OptionGroup,
  PillGroup,
  Screen,
  Text,
  TitleBar,
  useUndoToast,
} from "@/ui";

type History = Connection["history"];
type Calendar = NonNullable<Connection["calendars"]>[number];
type Mode = Calendar["mode"];

/** "Nowhere": REOS's own events stay in REOS. */
const NOWHERE = "";

/**
 * What to sync (Connections and Sync journey, screens 7 and 16): what syncs,
 * which way and how far back. For a calendar, also which calendars REOS
 * reads (read and write, busy only, or off) and which one REOS's own events
 * go to. Most people never open this: the main calendar is chosen for them.
 */
export default function SyncSettings() {
  const { t } = useTranslation();
  const toast = useUndoToast();
  const { id } = useLocalSearchParams<{ id: string }>();
  const { connections, reload } = useConnections();
  const connection = connections?.find((c) => String(c.id) === id);
  const [history, setHistory] = useState<History | null>(null);
  const [modes, setModes] = useState<Record<string, Mode>>({});
  const [write, setWrite] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [problem, setProblem] = useState<string | null>(null);

  if (!connection) {
    return <Screen>{null}</Screen>;
  }

  const chosen = history ?? connection.history;
  const calendars = connection.calendars ?? null;
  const modeOf = (calendar: Calendar) => modes[calendar.id] ?? calendar.mode;
  const savedWrite = calendars?.find((c) => c.write)?.id ?? NOWHERE;
  const writeTo = write ?? savedWrite;
  const writeChoices = (calendars ?? []).filter(
    (c) => c.writable && modeOf(c) === "full",
  );
  const calendarsChanged =
    Object.keys(modes).length > 0 || writeTo !== savedWrite;

  const save = async () => {
    setBusy(true);
    setProblem(null);
    const { response, error } = await api().PATCH("/connections/{connection}", {
      params: { path: { connection: connection.id } },
      body: {
        ...(history !== null || !calendarsChanged ? { history: chosen } : {}),
        ...(calendarsChanged
          ? {
              calendars: modes,
              // A calendar switched away from read + write can't receive REOS events.
              write_calendar: writeChoices.some((c) => c.id === writeTo)
                ? writeTo
                : NOWHERE,
            }
          : {}),
      },
    });
    setBusy(false);

    if (response.ok) {
      await reload();
      toast({
        message: t(
          calendarsChanged
            ? "connections.calendars.saved"
            : "connections.settings.saved",
        ),
      });
      router.back();
    } else {
      const errors = (error as { errors?: Record<string, string[]> } | undefined)
        ?.errors;
      setProblem(Object.values(errors ?? {})[0]?.[0] ?? null);
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

      {calendars ? (
        <>
          <Eyebrow>{t("connections.calendars.title")}</Eyebrow>
          <Text tone="muted">{t("connections.calendars.intro")}</Text>
          <Card>
            {calendars.map((calendar) => (
              <View key={calendar.id} style={styles.calendar}>
                <Text weight="semibold">{calendar.name}</Text>
                {calendar.primary || !calendar.writable ? (
                  <Text variant="caption" tone="muted">
                    {calendar.primary
                      ? t("connections.calendars.main")
                      : t("connections.calendars.readOnly")}
                  </Text>
                ) : null}
                <PillGroup<Mode>
                  label={t("connections.calendars.modeFor", {
                    calendar: calendar.name,
                  })}
                  value={modeOf(calendar)}
                  options={(["full", "busy", "off"] as const).map((value) => ({
                    value,
                    label: t(
                      value === "full" && !calendar.writable
                        ? "connections.calendars.modes.read"
                        : `connections.calendars.modes.${value}`,
                    ),
                  }))}
                  onChange={(value) =>
                    setModes((current) => ({ ...current, [calendar.id]: value }))
                  }
                />
              </View>
            ))}
          </Card>

          <Eyebrow>{t("connections.calendars.writeTo")}</Eyebrow>
          <Text tone="muted">{t("connections.calendars.writeHint")}</Text>
          <OptionGroup<string>
            label={t("connections.calendars.writeTo")}
            value={writeChoices.some((c) => c.id === writeTo) ? writeTo : NOWHERE}
            options={[
              ...writeChoices.map((c) => ({ value: c.id, label: c.name })),
              { value: NOWHERE, label: t("connections.calendars.writeNone") },
            ]}
            onChange={setWrite}
          />

          <Eyebrow>{t("connections.calendars.reads")}</Eyebrow>
          <Text tone="muted">{t("connections.calendars.readsBody")}</Text>
        </>
      ) : null}

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
      {problem ? <Callout tone="danger" title={problem} /> : null}
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
  calendar: { gap: theme.space.xs, paddingVertical: theme.space.sm },
  flex: { flex: 1 },
}));
