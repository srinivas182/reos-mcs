/**
 * Role-play and the skills assessment on the phone (A14.1a; agent screen
 * 7). The phone listens on the device (no audio leaves it) and writes the
 * words into the answer box; the client's lines and the result can be read
 * aloud. Private to the agent.
 */
import type { paths } from "@reos/api-client";
import type { TFunction } from "i18next";

import { api } from "@/lib/api";

type Got<P extends keyof paths> = paths[P] extends {
  get: { responses: { 200: { content: { "application/json": infer B } } } };
}
  ? B
  : never;

export type RolePlayOverview = Got<"/role-play">["data"];
export type RolePlaySession = Got<"/role-play/{rolePlay}">["data"];
export type PracticeType = RolePlayOverview["types"][number];
export type SkillType = PracticeType["type"];
export type Persona = PracticeType["personas"][number];

const fail = (error: unknown): never => {
  const e =
    (error as
      { errors?: Record<string, string[]>; message?: string } | undefined) ??
    {};
  throw new Error(
    Object.values(e.errors ?? {})[0]?.[0] ?? e.message ?? "error",
  );
};

export async function loadRolePlay(): Promise<RolePlayOverview | null> {
  const { data } = await api().GET("/role-play");

  return data ? data.data : null;
}

export async function loadSession(id: string): Promise<RolePlaySession | null> {
  const { data } = await api().GET("/role-play/{rolePlay}", {
    params: { path: { rolePlay: id } },
  });

  return data ? data.data : null;
}

export async function startRolePlay(
  type: SkillType,
  persona: Persona,
): Promise<RolePlaySession> {
  const { data, error } = await api().POST("/role-play", {
    body: { type, persona },
  });

  return data ? data.data : fail(error);
}

export async function startAssessment(): Promise<RolePlaySession> {
  const { data, error } = await api().POST("/role-play/assessment");

  return data ? data.data : fail(error);
}

export async function say(id: string, text: string): Promise<RolePlaySession> {
  const { data, error } = await api().POST("/role-play/{rolePlay}/turns", {
    params: { path: { rolePlay: id } },
    body: { text },
  });

  return data ? data.data : fail(error);
}

export async function finish(id: string): Promise<RolePlaySession> {
  const { data, error } = await api().POST("/role-play/{rolePlay}/finish", {
    params: { path: { rolePlay: id } },
  });

  return data ? data.data : fail(error);
}

/** Spoken words join what's already in the answer box. */
export function addWords(now: string, words: string): string {
  const said = words.trim();

  if (said === "") return now;

  return now.trim() === "" ? said : `${now.trimEnd()} ${said}`;
}

/** The newest line from the client, to read aloud; null when it's the agent's turn to have spoken last. */
export function lastClientLine(
  session: Pick<RolePlaySession, "turns">,
): string | null {
  const last = session.turns[session.turns.length - 1];

  return last?.role === "persona" ? last.text : null;
}

/** The result read aloud: the score, whether they agreed, and the three fixes. */
export function resultSpeech(
  session: Pick<RolePlaySession, "score" | "outcome" | "fixes">,
  t: TFunction,
): string {
  if (session.score === null) return "";

  return [
    t("roleplay.score", { score: session.score }),
    session.outcome === "won" ? t("roleplay.won") : t("roleplay.lost"),
    t("roleplay.fixesTitle"),
    ...session.fixes.map(
      (fix, i) =>
        `${i + 1}. ${fix.text} ${t("roleplay.tryLine", { line: fix.example })}`,
    ),
  ].join(" ");
}
