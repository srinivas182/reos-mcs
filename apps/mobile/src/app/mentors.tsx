import { router, useFocusEffect } from "expo-router";
import { GraduationCap, Users } from "lucide-react-native";
import { useCallback, useState } from "react";
import { View } from "react-native";
import { useTranslation } from "react-i18next";
import { StyleSheet } from "react-native-unistyles";

import { dollars, slotText } from "@/lib/bookings";
import {
  askForAnotherMentor,
  loadMentors,
  type MentorList,
} from "@/lib/licence";
import {
  Button,
  EmptyState,
  Icon,
  ListRow,
  Screen,
  SectionTitle,
  Text,
  TitleBar,
  useUndoToast,
} from "@/ui";

/**
 * Your mentors on the phone (candidate f04, A10.3c; agent screens 114 and
 * 116, A13.1a): the assigned mentor first (free), then the other mentors of
 * the agent's own brokerage with rating, price, points and next free hour;
 * Book; Ask for another mentor. Other brokerages' mentors never show.
 */
export default function MentorsScreen() {
  const { t, i18n } = useTranslation();
  const toast = useUndoToast();
  const [list, setList] = useState<MentorList | null>(null);

  useFocusEffect(
    useCallback(() => {
      void loadMentors().then(setList);
    }, []),
  );

  return (
    <Screen>
      <TitleBar title={t("mentors.title")} onBack={() => router.back()} />
      {list ? (
        <>
          <Text tone="muted">
            {list.brokerage
              ? t("mentors.purpose", { brokerage: list.brokerage })
              : t("mentors.purposeNone")}
          </Text>
          {list.mentors.length === 0 ? (
            <EmptyState
              icon={<Icon icon={Users} color="action" size={32} />}
              title={
                list.brokerage ? t("mentors.empty") : t("mentors.noBrokerage")
              }
              description={
                list.brokerage
                  ? t("mentors.emptyBody", { brokerage: list.brokerage })
                  : t("mentors.noBrokerageBody")
              }
            />
          ) : null}
          {list.mentors.some((m) => m.assigned) ? (
            <SectionTitle>{t("mentoring.agent.assigned")}</SectionTitle>
          ) : null}
          {list.mentors.map((m, i) => (
            <View key={m.id} style={styles.gap}>
              {i > 0 && list.mentors[i - 1]?.assigned && !m.assigned ? (
                <SectionTitle>{t("mentoring.agent.others")}</SectionTitle>
              ) : null}
              <ListRow
                icon={GraduationCap}
                tone={m.assigned ? "success" : "info"}
                title={m.name}
                subtitle={[
                  m.assigned ? t("mentoring.agent.free") : null,
                  m.topics.length > 0
                    ? m.topics
                        .map((x) =>
                          t(`mentoring.topics.${x as "buyers"}`, {
                            defaultValue: x,
                          }),
                        )
                        .join(", ")
                    : null,
                  m.rating === null
                    ? t("bookings.new")
                    : t("bookings.rating", {
                        rating: m.rating,
                        count: m.ratings,
                      }),
                  m.assigned
                    ? null
                    : m.price_30 === 0 && m.price_60 === 0
                      ? t("bookings.included")
                      : t("bookings.from", {
                          price: dollars(
                            Math.min(m.price_30, m.price_60) ||
                              Math.max(m.price_30, m.price_60),
                            i18n.language,
                          ),
                        }),
                  !m.assigned && m.points && m.price_60 > 0
                    ? t("mentoring.agent.points")
                    : null,
                  m.languages
                    .map((l) =>
                      t(`bookings.languages.${l === "es" ? "es" : "en"}`),
                    )
                    .join(", "),
                  m.next_slot
                    ? t("bookings.next", {
                        time: slotText(m.next_slot, i18n.language),
                      })
                    : null,
                ]
                  .filter(Boolean)
                  .join(" · ")}
                onPress={() =>
                  router.push({
                    pathname: "/bookings/[id]",
                    params: { id: String(m.user_id) },
                  })
                }
              />
              {m.assigned ? (
                <Text variant="caption" tone="muted">
                  {t("mentoring.agent.assignedNote")}
                </Text>
              ) : null}
            </View>
          ))}
          {list.brokerage ? (
            <Text variant="caption" tone="muted">
              {t("mentoring.agent.otherNote")}
            </Text>
          ) : null}
          {list.assigned !== null ? (
            list.change_requested ? (
              <Text tone="muted">{t("mentoring.agent.changeAsked")}</Text>
            ) : (
              <Button
                variant="ghost"
                label={t("mentoring.agent.change")}
                onPress={() =>
                  void askForAnotherMentor().then((next) => {
                    setList(next);
                    toast({ message: t("mentoring.agent.changed") });
                  })
                }
              />
            )
          ) : null}
          <Button
            variant="secondary"
            label={t("mentors.instructors")}
            onPress={() => router.push("/bookings")}
          />
        </>
      ) : null}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  gap: { gap: theme.space.xs },
}));
