/**
 * Spaced repetition by FSRS (version 5, its published default weights), the
 * same schedule the server runs (apps/platform/app/Practice/Flashcards/
 * Fsrs.php), so the phone can review offline (A9.2d, ADR 0035). The server
 * replays the synced reviews and stays the record.
 *
 * Ratings: 1 Again, 2 Hard, 3 Got it. Again brings the card back in ten
 * minutes; Hard and Got it schedule it for when recall is predicted to fall
 * to 90%.
 */
export const AGAIN = 1;
export const HARD = 2;
export const GOOD = 3;
export type Rating = typeof AGAIN | typeof HARD | typeof GOOD;

const W = [0.40255, 1.18385, 3.173, 15.69105, 7.1949, 0.5345, 1.4604, 0.0046, 1.54575, 0.1192, 1.01925, 1.9395, 0.11, 0.29605, 2.2698, 0.2315, 2.9898, 0.51655, 0.6621];
const DECAY = -0.5;
const FACTOR = 19 / 81;
const RETENTION = 0.9;
const AGAIN_MINUTES = 10;
const MAX_DAYS = 365;
const DAY = 86_400_000;

export type CardState = { stability: number | null; difficulty: number | null; reps: number; lapses: number; last_reviewed_at: string | null };
export type Scheduled = { stability: number; difficulty: number; reps: number; lapses: number; last_reviewed_at: string; due_at: string; interval_days: number };

const round4 = (n: number) => Math.round(n * 10_000) / 10_000;
const clampD = (d: number) => Math.max(1, Math.min(10, d));
const initialDifficulty = (rating: number) => W[4] - Math.exp(W[5] * (rating - 1)) + 1;

export const retrievability = (days: number, stability: number) => (1 + (FACTOR * days) / stability) ** DECAY;

export const interval = (stability: number) => Math.max(1, Math.min(MAX_DAYS, Math.round((stability / FACTOR) * (RETENTION ** (1 / DECAY) - 1))));

/** The card after one review at `at`. */
export function review(card: CardState, rating: Rating, at: Date): Scheduled {
  let stability: number;
  let difficulty: number;

  if (card.stability === null || card.difficulty === null || card.reps === 0 || card.last_reviewed_at === null) {
    stability = Math.max(W[rating - 1], 0.1);
    difficulty = clampD(initialDifficulty(rating));
  } else {
    const s = card.stability;
    const d = card.difficulty;
    const elapsed = Math.max(0, (at.getTime() - new Date(card.last_reviewed_at).getTime()) / DAY);
    const r = retrievability(elapsed, s);
    const next = d + (-W[6] * (rating - 3) * (10 - d)) / 9;
    difficulty = clampD(W[7] * initialDifficulty(4) + (1 - W[7]) * next);

    if (elapsed < 1) {
      stability = s * Math.exp(W[17] * (rating - 3 + W[18]));
    } else if (rating === AGAIN) {
      stability = Math.min(W[11] * d ** -W[12] * ((s + 1) ** W[13] - 1) * Math.exp((1 - r) * W[14]), s);
    } else {
      const hard = rating === HARD ? W[15] : 1;
      stability = s * (1 + Math.exp(W[8]) * (11 - d) * s ** -W[9] * (Math.exp((1 - r) * W[10]) - 1) * hard);
    }
  }

  stability = Math.max(0.1, stability);
  const days = rating === AGAIN ? 0 : interval(stability);
  const due = rating === AGAIN ? new Date(at.getTime() + AGAIN_MINUTES * 60_000) : new Date(at.getTime() + days * DAY);

  return {
    stability: round4(stability),
    difficulty: round4(difficulty),
    reps: card.reps + 1,
    lapses: card.lapses + (rating === AGAIN && card.reps > 0 ? 1 : 0),
    last_reviewed_at: at.toISOString(),
    due_at: due.toISOString(),
    interval_days: days,
  };
}
