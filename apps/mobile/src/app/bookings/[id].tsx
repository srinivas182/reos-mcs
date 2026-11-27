import { router, useLocalSearchParams } from "expo-router";
import * as WebBrowser from "expo-web-browser";
import { CalendarCheck } from "lucide-react-native";
import { useEffect, useState } from "react";
import { View } from "react-native";
import { useTranslation } from "react-i18next";
import { StyleSheet } from "react-native-unistyles";

import {
  book,
  dollars,
  type InstructorSlots,
  loadInstructor,
  slotText,
} from "@/lib/bookings";
import { loadMentors, type MentorList } from "@/lib/licence";
import {
  Button,
  Callout,
  Chip,
  PillGroup,
  Screen,
  SectionTitle,
  Text,
  TextField,
  TitleBar,
  useUndoToast,
} from "@/ui";

/**
 * One instructor (candidate screen 18, A10.2a): length, a free time and the
 * topic. Included sessions are booked at once; a paid one opens checkout on
 * reos.com in the browser (a live 1-on-1 service, App Review 3.1.3(d)).
 * A brokerage mentor (agent screen 117, A13.1a): free with the assigned
 * mentor, or paid with reward points (1,000 = $10 inside REOS) when the
 * brokerage accepts them.
 */
export default function InstructorScreen() {
  const { t, i18n } = useTranslation();
  const toast = useUndoToast();
  const { id } = useLocalSearchParams<{ id: string }>();
  const [data, setData] = useState<InstructorSlots | null>(null);
  const [minutes, setMinutes] = useState<"30" | "60">("60");
  const [slot, setSlot] = useState<string | null>(null);
  const [topic, setTopic] = useState("");
  const [busy, setBusy] = useState(false);
  const [problem, setProblem] = useState<string | null>(null);
  const [mentors, setMentors] = useState<MentorList | null>(null);
  const [payWith, setPayWith] = useState<"card" | "points">("card");

  useEffect(() => {
    void loadInstructor(Number(id)).then(setData);
    void loadMentors().then(setMentors);
  }, [id]);

  if (!data) {
    return <Screen>{null}</Screen>;
  }

  const offer = data.data;
  const mentor = mentors?.mentors.find((m) => m.user_id === offer.user_id);
  const free = mentor?.free === true;
  const price = free ? 0 : minutes === "30" ? offer.price_30 : offer.price_60;
  const balance = mentors?.points_balance ?? 0;
  const points = payWith === "points" && price > 0;
  const short = points && balance < price;
  const slots = (minutes === "30" ? data.slots_30 : data.slots_60).slice(0, 24);

  const submit = async () => {
    if (!slot) {
      return;
    }
    setBusy(true);
    setProblem(null);
    try {
      const result = await book({
        instructor: offer.user_id,
        starts_at: slot,
        minutes: Number(minutes),
        topic: topic.trim(),
        pay_with: points ? "points" : "card",
      });
      if (result.checkoutUrl) {
        await WebBrowser.openBrowserAsync(result.checkoutUrl);
      } else {
        toast({ message: t("bookings.statuses.confirmed") });
      }
      router.replace("/bookings");
    } catch (e) {
      setProblem(e instanceof Error ? e.message : String(e));
    } finally {
      setBusy(false);
    }
  };

  return (
    <Screen
      footer={
        <View style={styles.footer}>
          <Button
            icon={CalendarCheck}
            label={
              price === 0
                ? t("bookings.book")
                : points
                  ? t("bookings.points.bookWithPoints", {
                      points: price.toLocaleString(i18n.language),
                    })
                  : t("bookings.bookAndPay", {
                      price: dollars(price, i18n.language),
                    })
            }
            loading={busy}
            disabled={slot === null || topic.trim().length < 3 || short}
            fullWidth
            onPress={() => void submit()}
          />
        </View>
      }
    >
      <TitleBar title={offer.name} onBack={() => router.back()} />
      <Text tone="muted">
        {[offer.school, offer.topics.join(" · ")].filter(Boolean).join(" · ")}
      </Text>
      {offer.bio ? <Text>{offer.bio}</Text> : null}
      <PillGroup
        label={t("bookings.length")}
        value={minutes}
        options={(["30", "60"] as const).map((m) => {
          const cents = m === "30" ? offer.price_30 : offer.price_60;

          return {
            value: m,
            label: `${t("bookings.minutes", { count: Number(m) })} · ${free ? t("bookings.points.freeAssigned") : cents === 0 ? t("bookings.included") : dollars(cents, i18n.language)}`,
          };
        })}
        onChange={(m) => {
          setMinutes(m);
          setSlot(null);
        }}
      />
      {mentor?.points && price > 0 ? (
        <>
          <PillGroup
            label={t("bookings.points.payWith")}
            value={payWith}
            options={[
              { value: "card", label: t("bookings.points.card") },
              { value: "points", label: t("bookings.points.usePoints") },
            ]}
            onChange={setPayWith}
          />
          {points ? (
            <Text tone={short ? "danger" : "muted"}>
              {short
                ? t("bookings.points.notEnough")
                : t("bookings.points.pointsNeeded", {
                    points: price.toLocaleString(i18n.language),
                    balance: balance.toLocaleString(i18n.language),
                  })}
            </Text>
          ) : null}
        </>
      ) : null}
      <SectionTitle>{t("bookings.pickTime")}</SectionTitle>
      {slots.length === 0 ? (
        <Text tone="muted">{t("bookings.noTimes")}</Text>
      ) : null}
      <View style={styles.wrap}>
        {slots.map((s) => (
          <Chip
            key={s}
            tone={slot === s ? "info" : undefined}
            label={slotText(s, i18n.language)}
            onPress={() => setSlot(s)}
          />
        ))}
      </View>
      <TextField
        label={t("bookings.topic")}
        placeholder={t("bookings.topicPlaceholder")}
        value={topic}
        onChangeText={setTopic}
        maxLength={200}
      />
      <Text variant="caption" tone="muted">
        {t("bookings.rules")}
      </Text>
      {problem ? <Callout tone="danger" title={problem} live /> : null}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  footer: { paddingHorizontal: theme.m.gutter, paddingBottom: theme.space.xl },
  wrap: { flexDirection: "row", flexWrap: "wrap", gap: theme.space.sm },
}));
