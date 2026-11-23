import { router } from "expo-router";
import { useState } from "react";
import { View } from "react-native";
import { useTranslation } from "react-i18next";
import { StyleSheet } from "react-native-unistyles";

import { api } from "@/lib/api";
import { readPhoneContacts } from "@/lib/contacts";
import { sendPhoneContacts, useContacts } from "@/lib/crm";
import {
  Button,
  Callout,
  Card,
  Screen,
  Sheet,
  Text,
  TitleBar,
  useUndoToast,
} from "@/ui";

/**
 * Phone contacts (Connections and Sync journey, B7.1): add the people in
 * the phone's contacts so each person shows up once with what the CRMs know.
 * Read on the phone only when the person taps Add; never written back.
 */
export default function PhoneContacts() {
  const { t } = useTranslation();
  const toast = useUndoToast();
  const { phoneOn, reload } = useContacts();
  const [busy, setBusy] = useState(false);
  const [problem, setProblem] = useState<"denied" | "failed" | null>(null);
  const [confirming, setConfirming] = useState(false);

  const add = async () => {
    setBusy(true);
    setProblem(null);
    const read = await readPhoneContacts();

    if (!read.allowed) {
      setBusy(false);
      setProblem("denied");
      return;
    }

    const sent = await sendPhoneContacts(read.contacts);
    setBusy(false);

    if (sent === null) {
      setProblem("failed");
      return;
    }

    toast({ message: t("connections.phone.added", { count: sent }) });
    await reload();
  };

  const stop = async () => {
    setBusy(true);
    const { response } = await api().DELETE("/contacts/phone");
    setBusy(false);
    setConfirming(false);

    if (response.ok) {
      toast({ message: t("connections.phone.stopped") });
      await reload();
    }
  };

  return (
    <Screen>
      <TitleBar
        title={t("connections.phone.title")}
        onBack={() => router.back()}
      />
      <Card>
        <Text>{t("connections.phone.intro")}</Text>
        <Text tone="muted">{t("connections.phone.never")}</Text>
        <Text tone="muted">{t("connections.phone.match")}</Text>
      </Card>

      {problem ? (
        <Callout
          tone={problem === "denied" ? "warning" : "danger"}
          title={t(`connections.phone.${problem}`)}
          live
        />
      ) : null}

      <View style={styles.actions}>
        <Button
          label={
            busy
              ? t("connections.phone.adding")
              : phoneOn
                ? t("connections.phone.again")
                : t("connections.phone.add")
          }
          loading={busy}
          fullWidth
          onPress={() => void add()}
        />
        {phoneOn ? (
          <Button
            label={t("connections.phone.stop")}
            variant="ghost"
            fullWidth
            onPress={() => setConfirming(true)}
          />
        ) : null}
      </View>

      <Sheet
        open={confirming}
        onClose={() => setConfirming(false)}
        title={t("connections.phone.stop")}
      >
        <Text>{t("connections.phone.stopBody")}</Text>
        <View style={styles.actions}>
          <Button
            label={t("connections.phone.stop")}
            variant="destructive"
            loading={busy}
            fullWidth
            onPress={() => void stop()}
          />
          <Button
            label={t("connections.phone.keep")}
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
