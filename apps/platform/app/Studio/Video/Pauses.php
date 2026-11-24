<?php

declare(strict_types=1);

namespace App\Studio\Video;

/**
 * Takes the pauses out of a clip's speech (B12.2, ADR 0051; screen 168:
 * "pauses removed automatically"). ffmpeg's silencedetect finds the quiet
 * stretches; what's left, with a little air either side, is kept.
 */
final class Pauses
{
    /** Quiet for this long counts as a pause. */
    public const MIN_PAUSE = 0.7;

    /** Air kept either side of speech. */
    public const PAD = 0.15;

    /**
     * The stretches to keep, from silencedetect's log, for a clip of $seconds.
     * Everything is kept when the pauses would leave almost nothing.
     *
     * @return list<array{0: float, 1: float}>
     */
    public static function keep(string $log, float $seconds): array
    {
        preg_match_all('/silence_start:\s*(-?[\d.]+)/', $log, $starts);
        preg_match_all('/silence_end:\s*([\d.]+)/', $log, $ends);
        $keep = [];
        $from = 0.0;

        foreach ($starts[1] as $i => $start) {
            $quietFrom = max(0.0, (float) $start);
            $quietTo = isset($ends[1][$i]) ? (float) $ends[1][$i] : $seconds;

            if ($quietTo - $quietFrom < self::MIN_PAUSE) {
                continue;
            }

            $to = min($seconds, $quietFrom + self::PAD);

            if ($to - $from > 0.05) {
                $keep[] = [round($from, 2), round($to, 2)];
            }

            // A pause that runs to the end leaves nothing after it.
            $from = isset($ends[1][$i]) ? max($from, $quietTo - self::PAD) : $seconds;
        }

        if ($seconds - $from > 0.05) {
            $keep[] = [round($from, 2), round($seconds, 2)];
        }

        $kept = array_sum(array_map(static fn (array $r): float => $r[1] - $r[0], $keep));

        return $kept < 0.5 ? [[0.0, round($seconds, 2)]] : $keep;
    }

    /**
     * An ffmpeg select expression for the stretches.
     *
     * @param  list<array{0: float, 1: float}>  $keep
     */
    public static function expression(array $keep): string
    {
        return implode('+', array_map(static fn (array $r): string => sprintf('between(t\\,%.2F\\,%.2F)', $r[0], $r[1]), $keep));
    }
}
