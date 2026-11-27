<?php

declare(strict_types=1);

namespace App\Rewards;

use App\Agents\AgentModes;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Audit\AuditLogger;

/**
 * What each daily goal earns, set by the REOS super admin (A13.3a, ADR 0055).
 *
 * Points come only from the agent's own activity: conversations logged in
 * the CRM, a CE lesson finished, follow-ups done and a post published.
 * Nothing here pays for choosing or recommending a service provider (RESPA
 * §8). 1,000 points are worth $10 inside REOS (1 point = 1 cent) and have
 * no cash value.
 *
 * @phpstan-type Rates array{points: array<string, int>, all_done: int, daily_cap: int, targets: array<string, array<string, int>>}
 */
final readonly class PointRates
{
    public const KEY = 'points.rates';

    /** The daily goals, all from the agent's own activity. */
    public const GOALS = ['conversations', 'ce_lesson', 'follow_ups', 'post'];

    /** 1,000 points = $10 (points are cents inside REOS). */
    public const CENTS_PER_POINT = 1;

    /** @var Rates */
    public const DEFAULTS = [
        'points' => ['conversations' => 20, 'ce_lesson' => 25, 'follow_ups' => 15, 'post' => 15],
        'all_done' => 25,
        'daily_cap' => 200,
        // REOS's targets for each mode; a brokerage can set its own (0 turns a goal off).
        'targets' => [
            'launch' => ['conversations' => 5, 'ce_lesson' => 1, 'follow_ups' => 3, 'post' => 1],
            'grow' => ['conversations' => 10, 'ce_lesson' => 0, 'follow_ups' => 5, 'post' => 1],
            'lead' => ['conversations' => 8, 'ce_lesson' => 0, 'follow_ups' => 5, 'post' => 1],
            'own' => ['conversations' => 5, 'ce_lesson' => 0, 'follow_ups' => 5, 'post' => 1],
        ],
    ];

    public function __construct(private AuditLogger $audit) {}

    /** @return Rates */
    public function current(): array
    {
        $saved = PlatformSetting::query()->find(self::KEY)->value ?? [];
        $points = is_array($saved['points'] ?? null) ? $saved['points'] : [];
        $targets = is_array($saved['targets'] ?? null) ? $saved['targets'] : [];
        $rates = self::DEFAULTS;

        foreach (self::GOALS as $goal) {
            $rates['points'][$goal] = self::int($points[$goal] ?? null, $rates['points'][$goal]);

            foreach (AgentModes::GOALS as $mode) {
                $forMode = is_array($targets[$mode] ?? null) ? $targets[$mode] : [];
                $rates['targets'][$mode][$goal] = self::int($forMode[$goal] ?? null, $rates['targets'][$mode][$goal]);
            }
        }
        $rates['all_done'] = self::int($saved['all_done'] ?? null, $rates['all_done']);
        $rates['daily_cap'] = self::int($saved['daily_cap'] ?? null, $rates['daily_cap']);

        return $rates;
    }

    /** @param Rates $rates */
    public function save(array $rates, User $actor): void
    {
        $before = $this->current();
        PlatformSetting::query()->updateOrCreate(['key' => self::KEY], ['value' => $rates, 'updated_by' => $actor->id]);
        $this->audit->record('points.rates_updated', 'platform_setting', self::KEY, ['before' => $before, 'after' => $rates], $actor);
    }

    private static function int(mixed $value, int $default): int
    {
        return is_numeric($value) ? max(0, (int) $value) : $default;
    }
}
