import { router, useFocusEffect, useLocalSearchParams } from "expo-router";
import { ListChecks, MessageSquareText, Phone, StickyNote } from "lucide-react-native";
import { useCallback, useState } from "react";
import { Linking, View } from "react-native";
import { useTranslation } from "react-i18next";
import { StyleSheet } from "react-native-unistyles";

import { api } from "@/lib/api";
import { InsightBlock } from "@/features/contacts/InsightBlock";
import { askInsight, type Contact, type Insight, logTouch, reachUri } from "@/lib/crm";
import { dayAndMonth, timeAgo } from "@/lib/time";
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

type Task = { id: number; title: string; due_date: string | null; done: boolean };

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
 * add, which goes to every connected CRM that knows them. B8.3: the AI
 * summary and next step, Call and Text from the agent's own phone (logged
 * to the CRMs), and tasks, sent to the CRM that knows them.
 */
export default function ContactScreen() {
  const { t, i18n } = useTranslation();
  const toast = useUndoToast();
  const { id } = useLocalSearchParams<{ id: string }>();
  const [contact, setContact] = useState<Contact | null>(null);
  const [notes, setNotes] = useState<Note[] | null>(null);
  const [body, setBody] = useState("");
  const [busy, setBusy] = useState(false);
  const [insight, setInsight] = useState<Insight | null>(null);
  const [asking, setAsking] = useState(false);
  const [tasks, setTasks] = useState<Task[]>([]);
  const [taskTitle, setTaskTitle] = useState("");

  const load = useCallback(async () => {
    const { data } = await api().GET("/contacts/{contact}", {
      params: { path: { contact: id } },
    });
    if (data) {
      setContact(data.data as Contact);
      setNotes(data.notes as Note[]);
      setInsight((data.insight as Insight | null) ?? null);
      setTasks((data.tasks as Task[] | undefined) ?? []);
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

  const ask = async () => {
    setAsking(true);
    setInsight(await askInsight(id));
    setAsking(false);
  };

  const reach = async (channel: "call" | "text", phone: string) => {
    const draft = channel === "text" && insight?.channel === "text" ? (insight.draft ?? "") : "";
    await Linking.openURL(reachUri(channel, phone, draft));
    if (await logTouch(id, channel, draft === "" ? null : draft)) {
      toast({ message: t("crm.hub.touchLogged") });
      await load();
    }
  };

  const addTask = async () => {
    const { response, data } = await api().POST("/contacts/{contact}/tasks", {
      params: { path: { contact: id } },
      body: { title: taskTitle.trim() },
    });
    if (response.ok) {
      setTaskTitle("");
      const sentTo = (data as { sent_to?: string | null } | undefined)?.sent_to ?? null;
      toast({ message: sentTo ? t("crm.hub.taskSentTo", { tool: sentTo }) : t("crm.hub.taskSaved") });
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
        {contact.sources
          .filter((s) => s.campaign)
          .map((s) => (
            <Text key={`campaign-${s.source}`}>
              {t("crm.contact.campaign", {
                source: s.name,
                name: s.campaign,
                status: s.campaign_status ?? "",
              })}
            </Text>
          ))}
        {contact.stage ? (
          <Text>{`${t("crm.contact.stage")}: ${contact.stage}`}</Text>
        ) : null}
        {[...contact.emails, ...contact.phones].map((value) => (
          <Text key={value} tone="muted">
            {value}
          </Text>
        ))}
      </Card>

      {contact.phones[0] ? (
        <View style={styles.row}>
          <Button label={t("crm.hub.call")} icon={Phone} variant="secondary" onPress={() => void reach("call", contact.phones[0])} />
          <Button label={t("crm.hub.text")} icon={MessageSquareText} variant="secondary" onPress={() => void reach("text", contact.phones[0])} />
        </View>
      ) : null}

      <InsightBlock insight={insight} busy={asking} onAsk={() => void ask()} />

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

      <Eyebrow>{t("crm.hub.tasks")}</Eyebrow>
      <Card>
        <TextField label={t("crm.hub.addTask")} value={taskTitle} onChangeText={setTaskTitle} />
        <Button label={t("crm.hub.saveTask")} disabled={taskTitle.trim() === ""} onPress={() => void addTask()} />
      </Card>
      {tasks.length === 0 ? (
        <EmptyState
          icon={<Icon icon={ListChecks} color="action" size={28} />}
          title={t("crm.hub.noTasks")}
          description={t("crm.hub.noTasksBody")}
        />
      ) : null}
      {tasks.map((task) => (
        <Card key={`task-${task.id}`}>
          <Text>{task.title}</Text>
          <Text tone="muted" variant="caption">
            {task.done ? t("crm.hub.taskDone") : task.due_date ? t("crm.hub.dueOn", { date: dayAndMonth(`${task.due_date}T12:00:00`, i18n.language) }) : t("crm.hub.taskOpen")}
          </Text>
        </Card>
      ))}

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
