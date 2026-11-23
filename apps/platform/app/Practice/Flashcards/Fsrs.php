<?php

declare(strict_types=1);

namespace App\Practice\Flashcards;

use Carbon\CarbonImmutable;

/**
 * Spaced repetition by FSRS (version 5, its published default weights),
 * for flashcards (screen 31, A9.2d, ADR 0035). The phone runs the same
 * schedule offline (apps/mobile/src/lib/fsrs.ts); the server replays the
 * reviews it syncs and stays the record.
 *
 * Ratings: 1 Again, 2 Hard, 3 Got it (the journeys' three buttons; FSRS's
 * Good). Again brings the card back in ten minutes, so a miss comes back
 * sooner, in the same burst; Hard and Got it schedule it for the day its
 * recall is predicted to fall to 90%.
 */
final class Fsrs
{
    public const AGAIN = 1;

    public const HARD = 2;

    public const GOOD = 3;

    public const RETENTION = 0.9;

    public const AGAIN_MINUTES = 10;

    public const MAX_DAYS = 365;

    /** FSRS-5 default weights. */
    public const W = [0.40255, 1.18385, 3.173, 15.69105, 7.1949, 0.5345, 1.4604, 0.0046, 1.54575, 0.1192, 1.01925, 1.9395, 0.11, 0.29605, 2.2698, 0.2315, 2.9898, 0.51655, 0.6621];

    private const DECAY = -0.5;

    /** 0.9^(1 / DECAY) - 1, so an interval of S days lands at 90% recall. */
    private const FACTOR = 19 / 81;

    /**
     * The card after one review.
     *
     * @param  array{stability: float|null, difficulty: float|null, reps: int, lapses: int, last_reviewed_at: CarbonImmutable|null}  $card
     * @return array{stability: float, difficulty: float, reps: int, lapses: int, last_reviewed_at: CarbonImmutable, due_at: CarbonImmutable, interval_days: int}
     */
    public static function review(array $card, int $rating, CarbonImmutable $at): array
    {
        $rating = max(self::AGAIN, min(self::GOOD, $rating));
        $w = self::W;

        if ($card['stability'] === null || $card['difficulty'] === null || $card['reps'] === 0 || $card['last_reviewed_at'] === null) {
            $stability = max($w[$rating - 1], 0.1);
            $difficulty = self::clampD(self::initialDifficulty($rating));
        } else {
            $s = $card['stability'];
            $d = $card['difficulty'];
            $elapsed = max(0.0, $card['last_reviewed_at']->diffInSeconds($at) / 86400);
            $r = self::retrievability($elapsed, $s);
            $difficulty = self::nextDifficulty($d, $rating);

            if ($elapsed < 1) {
                // A second look the same day: short-term stability.
                $stability = $s * exp($w[17] * ($rating - 3 + $w[18]));
            } elseif ($rating === self::AGAIN) {
                $forget = $w[11] * $d ** -$w[12] * (($s + 1) ** $w[13] - 1) * exp((1 - $r) * $w[14]);
                $stability = min($forget, $s);
            } else {
                $hard = $rating === self::HARD ? $w[15] : 1.0;
                $stability = $s * (1 + exp($w[8]) * (11 - $d) * $s ** -$w[9] * (exp((1 - $r) * $w[10]) - 1) * $hard);
            }
        }

        $stability = max(0.1, $stability);
        $days = $rating === self::AGAIN ? 0 : self::interval($stability);

        return [
            'stability' => round($stability, 4),
            'difficulty' => round($difficulty, 4),
            'reps' => $card['reps'] + 1,
            'lapses' => $card['lapses'] + ($rating === self::AGAIN && $card['reps'] > 0 ? 1 : 0),
            'last_reviewed_at' => $at,
            'due_at' => $rating === self::AGAIN ? $at->addMinutes(self::AGAIN_MINUTES) : $at->addDays($days),
            'interval_days' => $days,
        ];
    }

    /** The chance of recalling a card `days` after its last review. */
    public static function retrievability(float $days, float $stability): float
    {
        return (1 + self::FACTOR * $days / $stability) ** self::DECAY;
    }

    /** Whole days until recall falls to 90%: at least one, at most a year. */
    public static function interval(float $stability): int
    {
        $days = $stability / self::FACTOR * (self::RETENTION ** (1 / self::DECAY) - 1);

        return (int) max(1, min(self::MAX_DAYS, round($days)));
    }

    private static function initialDifficulty(int $rating): float
    {
        return self::W[4] - exp(self::W[5] * ($rating - 1)) + 1;
    }

    private static function nextDifficulty(float $d, int $rating): float
    {
        $next = $d + (-self::W[6] * ($rating - 3)) * (10 - $d) / 9;

        // Mean reversion towards a new card's difficulty for an easy answer.
        return self::clampD(self::W[7] * self::initialDifficulty(4) + (1 - self::W[7]) * $next);
    }

    private static function clampD(float $d): float
    {
        return max(1.0, min(10.0, $d));
    }
}
