import type { TFunction } from "i18next";

import { addWords, lastClientLine, resultSpeech } from "./role-play";

const t = ((key: string, values?: Record<string, unknown>) =>
  values ? `${key} ${JSON.stringify(values)}` : key) as unknown as TFunction;

describe("role-play on the phone (A14.1a)", () => {
  it("adds spoken words to the answer box", () => {
    expect(addWords("", "  I understand ")).toBe("I understand");
    expect(addWords("I understand.", "Can we meet Thursday?")).toBe(
      "I understand. Can we meet Thursday?",
    );
    expect(addWords("Hi Mark", "   ")).toBe("Hi Mark");
  });

  it("reads the newest client line only when the client spoke last", () => {
    expect(
      lastClientLine({ turns: [{ role: "persona", text: "Why you?" }] }),
    ).toBe("Why you?");
    expect(
      lastClientLine({
        turns: [
          { role: "persona", text: "Why you?" },
          { role: "agent", text: "Because." },
        ],
      }),
    ).toBeNull();
    expect(lastClientLine({ turns: [] })).toBeNull();
  });

  it("says the score, the outcome and the three fixes", () => {
    const fix = (n: number) => ({
      dimension: "ask" as const,
      text: `Fix ${n}.`,
      example: `Line ${n}`,
      lesson: "asking_for_business" as const,
    });
    const text = resultSpeech(
      { score: 72, outcome: "won", fixes: [fix(1), fix(2), fix(3)] },
      t,
    );

    expect(text).toContain('roleplay.score {"score":72}');
    expect(text).toContain("roleplay.won");
    expect(text).toContain('3. Fix 3. roleplay.tryLine {"line":"Line 3"}');
    expect(resultSpeech({ score: null, outcome: null, fixes: [] }, t)).toBe("");
  });
});
