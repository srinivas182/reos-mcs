<?php

declare(strict_types=1);

namespace App\Features;

use App\Models\FeatureFlag;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The automatic rollback (A6.2): every minute, saves each flag's uses and
 * errors per minute, then steps back any flag whose error rate stayed at
 * or above its threshold for the last 10 minutes (at least 10 uses each
 * minute, counted only since its last rollout change). Canary cells wait
 * for the AWS build; until then this works per feature.
 */
final readonly class FlagWatch
{
    public const WINDOW_MINUTES = 10;

    /** Fewer uses than this in a minute is too little to judge. */
    public const MIN_USES = 10;

    public function __construct(
        private Flags $flags,
        private FlagUsage $usage,
        private Rollouts $rollouts,
    ) {}

    /** @return list<string> the flags stepped back */
    public function run(?CarbonImmutable $now = null): array
    {
        $now = ($now ?? CarbonImmutable::now())->startOfMinute();
        $flags = $this->flags->all();
        $this->flush($flags, $now);
        $stepped = [];

        foreach ($flags as $flag) {
            if ($flag->killed() || $flag->stage === RolloutStage::Off) {
                continue;
            }

            $rate = $this->sustainedRate($flag, $now);

            if ($rate !== null && $rate >= $flag->error_threshold) {
                $this->rollouts->rollBack($flag, $rate);
                $stepped[] = $flag->key;
            }
        }

        return $stepped;
    }

    /**
     * Copies the last finished minutes from the cache (safe to run twice).
     *
     * @param  list<FeatureFlag>  $flags
     */
    private function flush(array $flags, CarbonImmutable $now): void
    {
        foreach ($flags as $flag) {
            for ($back = 1; $back <= self::WINDOW_MINUTES; $back++) {
                $minute = $now->subMinutes($back);
                $uses = $this->usage->read($flag->key, $minute, 'uses');

                if ($uses === 0) {
                    continue;
                }

                DB::table('feature_flag_metrics')->upsert([[
                    'feature_flag_id' => $flag->id,
                    'minute' => $minute->toDateTimeString(),
                    'uses' => $uses,
                    'errors' => $this->usage->read($flag->key, $minute, 'errors'),
                ]], ['feature_flag_id', 'minute'], ['uses', 'errors']);
            }
        }
    }

    /** The lowest per-minute error rate (per 100 uses) over the window, or null if any minute is missing or thin. */
    private function sustainedRate(FeatureFlag $flag, CarbonImmutable $now): ?float
    {
        $from = $now->subMinutes(self::WINDOW_MINUTES);

        if ($flag->stage_changed_at !== null && $flag->stage_changed_at->greaterThan($from)) {
            return null;
        }

        $rows = DB::table('feature_flag_metrics')
            ->where('feature_flag_id', $flag->id)
            ->where('minute', '>=', $from->toDateTimeString())
            ->where('minute', '<', $now->toDateTimeString())
            ->get(['uses', 'errors']);

        if ($rows->count() < self::WINDOW_MINUTES) {
            return null;
        }

        $lowest = null;

        foreach ($rows as $row) {
            $uses = is_numeric($row->uses) ? (int) $row->uses : 0;
            $errors = is_numeric($row->errors) ? (int) $row->errors : 0;

            if ($uses < self::MIN_USES) {
                return null;
            }

            $rate = $errors * 100 / $uses;
            $lowest = $lowest === null ? $rate : min($lowest, $rate);
        }

        return $lowest;
    }

    /**
     * Uses and errors since the flag's last rollout change (screen 70).
     *
     * @return array{uses: int, errors: int}
     */
    public static function sinceRollout(FeatureFlag $flag): array
    {
        $row = DB::table('feature_flag_metrics')
            ->where('feature_flag_id', $flag->id)
            ->when($flag->stage_changed_at !== null, fn ($q) => $q->where('minute', '>=', $flag->stage_changed_at?->copy()->startOfMinute()->toDateTimeString()))
            ->selectRaw('COALESCE(SUM(uses), 0) AS uses, COALESCE(SUM(errors), 0) AS errors')
            ->first();

        return [
            'uses' => is_object($row) && is_numeric($row->uses ?? null) ? (int) $row->uses : 0,
            'errors' => is_object($row) && is_numeric($row->errors ?? null) ? (int) $row->errors : 0,
        ];
    }
}
