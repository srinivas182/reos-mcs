import { router, useLocalSearchParams } from "expo-router";
import { ArrowLeftRight, Check, Lock } from "lucide-react-native";
import { useState } from "react";
import { View } from "react-native";
import { useTranslation } from "react-i18next";
import { StyleSheet } from "react-native-unistyles";

import { connectTool, toolInitials, useCatalogue } from "@/lib/connections";
import {
  Button,
  Callout,
  Card,
  Eyebrow,
  Icon,
  Screen,
  Text,
  TextField,
} from "@/ui";

/**
 * Sign in on the vendor's page (Connections and Sync journey, screen 5):
 * exactly what REOS will be able to do and what it never does, then the
 * tool's own sign-in in a secure browser sheet. Tools connected with the
 * person's own API key (Follow Up Boss, B7.2) take the key here instead.
 */
export default function ConsentScreen() {
  const { t } = useTranslation();
  const { tool } = useLocalSearchParams<{ tool: string }>();
  const { items } = useCatalogue();
  const item = items?.find((i) => i.id === tool);
  const [busy, setBusy] = useState(false);
  const [problem, setProblem] = useState<string | null>(null);
  const [apiKey, setApiKey] = useState("");

  if (!item) {
    return <Screen>{null}</Screen>;
  }

  const go = async () => {
    setBusy(true);
    setProblem(null);
    const withKey = item.connects_with === "api_key";
    const result = await connectTool(item.id, withKey ? apiKey.trim() : undefined);
    setBusy(false);

    if (result.outcome === "connected" && result.connectionId !== null) {
      router.replace({
        pathname: "/connections/connected/[id]",
        params: { id: String(result.connectionId) },
      });
    } else if (result.outcome === "failed") {
      setProblem(
        result.reason?.startsWith("connections.")
          ? t(result.reason as "connections.errors.refused")
          : (result.reason ?? t("connections.errors.vendorUnavailable")),
      );
    }
  };

  return (
    <Screen
      footer={
        <View style={styles.footer}>
          {problem ? <Callout tone="danger" title={problem} live /> : null}
          <Button
            label={
              item.connects_with === "api_key"
                ? t("connections.consent.connectKey", { tool: item.name })
                : t("connections.consent.continue", { tool: item.name })
            }
            loading={busy}
            disabled={item.connects_with === "api_key" && apiKey.trim().length < 4}
            fullWidth
            onPress={() => void go()}
          />
          <Button
            label={t("connections.consent.cancel")}
            variant="ghost"
            onPress={() => router.back()}
          />
        </View>
      }
    >
      <View
        style={styles.tiles}
        accessibilityElementsHidden
        importantForAccessibility="no-hide-descendants"
      >
        <View style={[styles.tile, styles.reos]}>
          <Text weight="bold" tone="onAction" heading={false}>
            R
          </Text>
        </View>
        <Icon icon={ArrowLeftRight} color="textMuted" size={20} />
        <View style={styles.tile}>
          <Text weight="bold" tone="onAction" heading={false}>
            {toolInitials(item.name)}
          </Text>
        </View>
      </View>
      <Text variant="title" style={styles.center}>
        {t("connections.consent.title", { tool: item.name })}
      </Text>
      <Text tone="muted" style={styles.center}>
        {item.connects_with === "api_key"
          ? t("connections.consent.keyBody", { tool: item.name })
          : t("connections.consent.body", { tool: item.name })}
      </Text>
      {item.connects_with === "api_key" ? (
        <TextField
          label={t("connections.consent.keyLabel", { tool: item.name })}
          hint={t("connections.consent.keyHint", { tool: item.name })}
          value={apiKey}
          onChangeText={setApiKey}
          secureTextEntry
          autoCapitalize="none"
          autoCorrect={false}
        />
      ) : null}

      {item.consent ? (
        <Card>
          <Eyebrow>{t("connections.consent.willBeAbleTo")}</Eyebrow>
          {item.consent.can.map((key) => (
            <View key={key} style={styles.line}>
              <Icon icon={Check} color="success" size={20} />
              <Text style={styles.flex}>
                {t(`connections.consent.can.${key}`)}
              </Text>
            </View>
          ))}
          {item.consent.never.map((key) => (
            <View key={key} style={styles.line}>
              <Icon icon={Lock} color="danger" size={20} />
              <Text style={styles.flex}>
                {t(`connections.consent.never.${key}`)}
              </Text>
            </View>
          ))}
        </Card>
      ) : null}
      <Text variant="caption" tone="muted" style={styles.center}>
        {t("connections.consent.footer")}
      </Text>
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  footer: {
    gap: theme.space.xs,
    paddingHorizontal: theme.m.gutter,
    paddingBottom: theme.space.xl,
  },
  tiles: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    gap: theme.space.md,
    marginTop: theme.space.lg,
  },
  tile: {
    width: 56,
    height: 56,
    borderRadius: theme.m.radius.button,
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: theme.colors.action,
  },
  reos: { backgroundColor: theme.colors.heroBg },
  center: { textAlign: "center" },
  line: {
    flexDirection: "row",
    alignItems: "center",
    gap: theme.space.sm,
    minHeight: theme.touch - theme.space.md,
  },
  flex: { flex: 1 },
}));
