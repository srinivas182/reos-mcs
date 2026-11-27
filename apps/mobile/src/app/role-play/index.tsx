import { router, useFocusEffect } from "expo-router";
import { ClipboardCheck, Theater } from "lucide-react-native";
import { useCallback, useState } from "react";
import { useTranslation } from "react-i18next";

import {
  loadRolePlay,
  type RolePlayOverview,
  type RolePlaySession,
  startAssessment,
  startRolePlay,
} from "@/lib/role-play";
import {
  Button,
  Callout,
  Card,
  EmptyState,
  Icon,
  ListRow,
  Screen,
  SectionTitle,
  Text,
  TitleBar,
} from "@/ui";

/**
 * Role-play on the phone (A14.1a; agent screen 7 for the skills
 * assessment): the assessment, the five skills with their people, and
 * recent practice. Private to the agent.
 */
export default function RolePlayScreen() {
  const { t } = useTranslation();
  const [view, setView] = useState<RolePlayOverview | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useFocusEffect(
    useCallback(() => {
      void loadRolePlay().then(setView);
    }, []),
  );

  const open = async (work: () => Promise<RolePlaySession>) => {
    setBusy(true);
    setError(null);
    try {
      const session = await work();
      router.push({ pathname: "/role-play/[id]", params: { id: session.id } });
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setBusy(false);
    }
  };
  const assessment = view?.assessment ?? null;
  const skills = (assessment?.weakest ?? [])
    .map((type) => t(`roleplay.types.${type}`))
    .join(", ");

  return (
    <Screen>
      <TitleBar title={t("roleplay.title")} onBack={() => router.back()} />
      <Text tone="muted">{t("roleplay.purpose")}</Text>
      {error ? <Callout tone="danger" title={error} /> : null}

      <Card>
        <Icon icon={ClipboardCheck} color="action" size={28} />
        <Text weight="semibold">{t("roleplay.assessment.title")}</Text>
        <Text tone="muted">{t("roleplay.assessment.purpose")}</Text>
        {assessment && !assessment.completed ? (
          <Text>
            {t("roleplay.assessment.progress", {
              done: assessment.done,
              total: assessment.total,
            })}
          </Text>
        ) : null}
        {assessment?.completed ? (
          <Text weight="semibold">
            {t("roleplay.assessment.done", { skills })}
          </Text>
        ) : null}
        {assessment?.completed
          ? assessment.results.map((r) => (
              <Text
                key={r.type}
              >{`${t(`roleplay.types.${r.type}`)}: ${t("roleplay.score", { score: r.score })}`}</Text>
            ))
          : null}
        <Button
          label={
            !assessment
              ? t("roleplay.assessment.start")
              : assessment.completed
                ? t("roleplay.assessment.retake")
                : t("roleplay.assessment.continue", { n: assessment.done + 1 })
          }
          loading={busy}
          onPress={() => void open(startAssessment)}
        />
      </Card>

      <SectionTitle>{t("roleplay.practiceTitle")}</SectionTitle>
      {view?.types.map((item) => (
        <Card key={item.type}>
          <Text weight="semibold">{t(`roleplay.types.${item.type}`)}</Text>
          <Text tone="muted">{t(`roleplay.typeHints.${item.type}`)}</Text>
          {item.best !== null ? (
            <Text>{t("roleplay.best", { score: item.best })}</Text>
          ) : null}
          {item.personas.map((persona) => (
            <Button
              key={persona}
              label={t(`roleplay.personas.${persona}.name`)}
              variant="secondary"
              disabled={busy}
              onPress={() => void open(() => startRolePlay(item.type, persona))}
            />
          ))}
        </Card>
      ))}

      <SectionTitle>{t("roleplay.recent")}</SectionTitle>
      {view && view.recent.length === 0 ? (
        <EmptyState
          icon={<Icon icon={Theater} color="action" size={32} />}
          title={t("roleplay.none")}
          description={t("roleplay.noneHint")}
        />
      ) : null}
      {view?.recent.map((item) => (
        <ListRow
          key={item.id}
          icon={Theater}
          tone="info"
          title={t(`roleplay.types.${item.type}`)}
          subtitle={`${t(`roleplay.personas.${item.persona}.name`)} · ${item.score !== null ? t("roleplay.score", { score: item.score }) : t("roleplay.inProgress")}`}
          onPress={() =>
            router.push({
              pathname: "/role-play/[id]",
              params: { id: item.id },
            })
          }
        />
      ))}
      <Text tone="muted">{t("roleplay.private")}</Text>
    </Screen>
  );
}
