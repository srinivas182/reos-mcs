import { router, useFocusEffect, useLocalSearchParams } from "expo-router";
import { StickyNote } from "lucide-react-native";
import { useCallback, useState } from "react";
import { View } from "react-native";
import { useTranslation } from "react-i18next";
import { StyleSheet } from "react-native-unistyles";

import { api } from "@/lib/api";
import type { Contact } from "@/lib/crm";
import { timeAgo } from "@/lib/time";
import {
  Button,
  Card,
  Chip,
  EmptyState,
  Eyebrow,
  Icon,
  Screen,
  Text,
  TextField,
  TitleBar,
  useUndoToast,
} from "@/ui";

type Note = {
  id: number;
  body: string;
  source: string;
  source_name: string;
  sent_to: string[];
  at: string | null;
};

/**
 * One person, all sources (Connections and Sync journey, screen 14, B7.2):
 * where they come from, their notes from REOS and the CRMs, and a note to
 * add, which goes to every connected CRM that knows them.
 */
export default function ContactScreen() {
  const { t, i18n } = useTranslation();
  const toast = useUndoToast();
  const { id } = useLocalSearchParams<{ id: string }>();
  const [contact, setContact] = useState<Contact | null>(null);
  const [notes, setNotes] = useState<Note[] | null>(null);
  const [body, setBody] = useState("");
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    const { data } = await api().GET("/contacts/{contact}", {
      params: { path: { contact: id } },
    });
    if (data) {
      setContact(data.data as Contact);
      setNotes(data.notes as Note[]);
    }
  }, [id]);

  useFocusEffect(
    useCallback(() => {
      void load();
    }, [load]),
  );

  const save = async () => {
    setBusy(true);
    const { response } = await api().POST("/contacts/{contact}/notes", {
      params: { path: { contact: id } },
      body: { body: body.trim() },
    });
    setBusy(false);

    if (response.ok) {
      setBody("");
      toast({ message: t("crm.contact.saved") });
      await load();
    }
  };

  if (!contact) {
    return <Screen>{null}</Screen>;
  }

  return (
    <Screen>
      <TitleBar title={contact.name} onBack={() => router.back()} />
      <Card>
        <Eyebrow>{t("crm.contact.sources")}</Eyebrow>
        <View style={styles.row}>
          {contact.sources.map((s) => (
            <Chip key={s.source} label={s.name} tone="neutral" />
          ))}
        </View>
        {contact.stage ? (
          <Text>{`${t("crm.contact.stage")}: ${contact.stage}`}</Text>
        ) : null}
        {[...contact.emails, ...contact.phones].map((value) => (
          <Text key={value} tone="muted">
            {value}
          </Text>
        ))}
      </Card>

      <Card>
        <TextField
          label={t("crm.contact.addNote")}
          value={body}
          onChangeText={setBody}
          multiline
        />
        <Button
          label={t("crm.contact.save")}
          loading={busy}
          disabled={body.trim() === ""}
          onPress={() => void save()}
        />
      </Card>

      <Eyebrow>{t("crm.contact.notes")}</Eyebrow>
      {notes !== null && notes.length === 0 ? (
        <EmptyState
          icon={<Icon icon={StickyNote} color="action" size={28} />}
          title={t("crm.contact.noNotes")}
          description={t("crm.contact.noNotesBody")}
        />
      ) : null}
      {(notes ?? []).map((note) => (
        <Card key={note.id}>
          <Text>{note.body}</Text>
          <Text tone="muted" variant="caption">
            {[
              t("crm.contact.from", { source: note.source_name }),
              note.at ? timeAgo(note.at, i18n.language) : null,
              note.sent_to.length > 0
                ? t("crm.contact.sentTo", { tools: note.sent_to.join(", ") })
                : null,
            ]
              .filter(Boolean)
              .join(" · ")}
          </Text>
        </Card>
      ))}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  row: { flexDirection: "row", flexWrap: "wrap", gap: theme.space.xs },
}));
