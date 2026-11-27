/**
 * Agent modes on the phone (A11.1a; agent screens 4, 5, 6, 146): the goal
 * that sets Today, work-style flags and suggested switches.
 */
import type { paths } from "@reos/api-client";
import { type Href, useFocusEffect } from "expo-router";
import { useCallback, useState } from "react";

import { api } from "@/lib/api";

type Got<P extends keyof paths> = paths[P] extends {
  get: { responses: { 200: { content: { "application/json": infer B } } } };
}
  ? B
  : never;

export type ModeState = Got<"/agent/mode">["data"];
export type Goal = "launch" | "grow" | "lead" | "own";
export type Flag =
  "part_time" | "bilingual" | "specialist" | "referral_only" | "returning";
export const GOALS: Goal[] = ["launch", "grow", "lead", "own"];
export const FLAGS: Flag[] = [
  "part_time",
  "bilingual",
  "specialist",
  "referral_only",
  "returning",
];

const fail = (error: unknown): never => {
  const e =
    (error as
      { errors?: Record<string, string[]>; message?: string } | undefined) ??
    {};
  throw new Error(
    Object.values(e.errors ?? {})[0]?.[0] ?? e.message ?? "error",
  );
};

const take = ({
  data,
  error,
}: {
  data?: { data: ModeState };
  error?: unknown;
}): ModeState => (data ? data.data : fail(error));

export const setGoal = async (
  goal: Goal,
  reason: "setup" | "manual" | "suggested",
) => take(await api().PUT("/agent/mode", { body: { goal, reason } }));
export const setFlags = async (flags: Flag[]) =>
  take(await api().PUT("/agent/mode/flags", { body: { flags } }));
export const snooze = async () => take(await api().POST("/agent/mode/snooze"));

/** The agent's mode, loaded when the screen comes into view; null for other roles. */
export function useMode(enabled = true) {
  const [mode, setMode] = useState<ModeState | null>(null);

  useFocusEffect(
    useCallback(() => {
      if (!enabled) return;
      void api()
        .GET("/agent/mode")
        .then(({ data }) => setMode(data?.data ?? null))
        .catch(() => undefined);
    }, [enabled]),
  );

  return { mode, setMode };
}

/** Today, the 90-day plan and the scorecard (A11.1b; agent screens 10, 14, 15, 110). */
export type AgentToday = Got<"/agent/today">["data"];
export type TodayItem = AgentToday["items"][number];
export type AgentPlan = Got<"/agent/plan">["data"];
export type PlanTask = AgentPlan["tasks"][number];
export type Scorecard = Got<"/agent/scorecard">["data"];

const takePlan = ({
  data,
  error,
}: {
  data?: { data: AgentPlan };
  error?: unknown;
}): AgentPlan => (data ? data.data : fail(error));

export const tickTask = async (code: string, week: number, done: boolean) =>
  takePlan(
    await api().PUT("/agent/plan/tasks/{code}", {
      params: { path: { code } },
      body: { week, done },
    }),
  );
export const makePromise = async () =>
  takePlan(await api().POST("/agent/plan/promise"));

/** Where a plan task or day-one step is done on the phone; null when it's done anywhere. */
export function planRoute(link: string | null): Href | null {
  switch (link) {
    case "connections":
      return "/connections";
    case "contacts":
      return "/contacts";
    case "deals":
      return "/deals";
    case "listings":
    case "announce":
      return "/deals/listings";
    case "learn":
      return "/learn";
    case "mentors":
      return "/mentors";
    case "role_play":
      return "/role-play";
    default:
      return null;
  }
}

/** Today for agents, loaded when the screen comes into view. */
export function useAgentToday(enabled = true) {
  const [today, setToday] = useState<AgentToday | null>(null);
  const reload = useCallback(async () => {
    if (!enabled) return;
    const { data } = await api().GET("/agent/today");
    setToday(data?.data ?? null);
  }, [enabled]);

  useFocusEffect(
    useCallback(() => {
      void reload().catch(() => undefined);
    }, [reload]),
  );

  return { today, reload };
}

/** The plan and this week's scorecard. */
export function usePlan() {
  const [plan, setPlan] = useState<AgentPlan | null>(null);
  const [scorecard, setScorecard] = useState<Scorecard | null>(null);

  useFocusEffect(
    useCallback(() => {
      void api()
        .GET("/agent/plan")
        .then(({ data }) => setPlan(data?.data ?? null))
        .catch(() => undefined);
      void api()
        .GET("/agent/scorecard")
        .then(({ data }) => setScorecard(data?.data ?? null))
        .catch(() => undefined);
    }, []),
  );

  return { plan, setPlan, scorecard };
}

/** Codes the server sends, for typed translation keys. */
export type PlanTaskCode =
  | "connect_tools"
  | "import_contacts"
  | "announce_licence"
  | "first_ce_lesson"
  | "call_sphere"
  | "ask_referrals"
  | "mentor_checkin"
  | "book_consultation"
  | "listing_presentation"
  | "review_contract"
  | "plan_closing"
  | "pipeline_review"
  | "past_client_touch"
  | "anniversary_touch"
  | "map_your_hours"
  | "write_role"
  | "interview_candidates"
  | "team_huddle"
  | "choose_model"
  | "broker_course"
  | "business_plan"
  | "comp_plan"
  | "broker_licence"
  | "go_live_checklist";
export type PlanPhase =
  | "setup"
  | "conversations"
  | "appointments"
  | "contract"
  | "pipeline"
  | "referrals"
  | "repeat"
  | "capacity"
  | "first_hire"
  | "team"
  | "model"
  | "business_plan"
  | "launch";
export type ScoreMetric =
  "conversations" | "appointments" | "clients" | "ce_hours";
export type TodayStatKey =
  | "week"
  | "conversations"
  | "appointments"
  | "pipeline"
  | "under_contract"
  | "hot_today"
  | "closings_12m";
export type DayOneCode =
  "import_contacts" | "announce_licence" | "connect_tools";

/** The morning brief (A11.1c; screen 184). */
export type Brief = Got<"/agent/brief">["data"];
export type BriefCommand = Brief["commands"][number];
export type BriefSettings = Got<"/agent/brief/settings">["data"];
export type BriefSection =
  "todos" | "appointments" | "deadlines" | "leads" | "ce";
export const BRIEF_SECTIONS: BriefSection[] = [
  "todos",
  "appointments",
  "deadlines",
  "leads",
  "ce",
];
export const BRIEF_TIMES = [
  "06:00",
  "06:30",
  "07:00",
  "07:30",
  "08:00",
  "08:30",
  "09:00",
] as const;

export async function saveBriefSettings(
  settings: BriefSettings,
): Promise<BriefSettings> {
  const { data, error } = await api().PUT("/agent/brief/settings", {
    body: settings,
  });

  return data ? data.data : fail(error);
}

/** Today's brief and its settings. */
export function useBrief() {
  const [brief, setBrief] = useState<Brief | null>(null);
  const [settings, setSettings] = useState<BriefSettings | null>(null);

  useFocusEffect(
    useCallback(() => {
      void api()
        .GET("/agent/brief")
        .then(({ data }) => setBrief(data?.data ?? null))
        .catch(() => undefined);
      void api()
        .GET("/agent/brief/settings")
        .then(({ data }) => setSettings(data?.data ?? null))
        .catch(() => undefined);
    }, []),
  );

  return { brief, settings, setSettings };
}

/** Words that say nothing about which command was meant, in English and Spanish. */
const FILLER = new Set([
  "the",
  "and",
  "please",
  "now",
  "for",
  "with",
  "del",
  "los",
  "las",
  "por",
  "favor",
  "con",
  "una",
  "uno",
]);

const words = (text: string): string[] =>
  text
    .toLocaleLowerCase()
    .normalize("NFD")
    .replace(/[̀-ͯ]/g, "")
    .replace(/[^\p{L}\p{N}\s]/gu, " ")
    .split(/\s+/)
    .filter((w) => w.length > 2 && !FILLER.has(w));

/**
 * The command a spoken phrase asks for ("Call Ana", "open the Oak Ridge
 * deal"): the one sharing the most words with what was heard, or null.
 */
export function matchCommand(
  heard: string,
  commands: BriefCommand[],
): BriefCommand | null {
  const said = new Set(words(heard));
  let best: BriefCommand | null = null;
  let score = 0;

  for (const command of commands) {
    const shared = words(command.label).filter((w) => said.has(w)).length;
    if (shared > score) {
      best = command;
      score = shared;
    }
  }

  return best;
}
