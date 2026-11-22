<?php

declare(strict_types=1);

namespace App\Monitoring;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * The platform's health, checked every minute (A6.1; `reos:health:check`):
 * the database, the cache, the queues and file storage. Uptime on screen 58
 * and the public status page is the share of checks that passed.
 */
final readonly class HealthChecks
{
    /** Kept 90 days. */
    public const KEEP_DAYS = 90;

    public function __construct(private Cache $cache) {}

    /** @return array{ok: bool, parts: array<string, array{ok: bool, ms: int}>} */
    public function run(): array
    {
        $parts = [
            'database' => $this->time(static fn () => DB::select('select 1')),
            'cache' => $this->time(function (): void {
                $this->cache->put('health:ping', 'pong', 60);

                if ($this->cache->get('health:ping') !== 'pong') {
                    throw new \RuntimeException('Cache did not answer.');
                }
            }),
            'queue' => $this->time(static fn () => Queue::size('default')),
            'storage' => $this->time(static fn () => Storage::disk(config()->string('reos.content.disk'))->exists('health-check')),
        ];
        $ok = ! in_array(false, array_column($parts, 'ok'), true);

        DB::table('health_checks')->insert(['checked_at' => now(), 'ok' => $ok, 'parts' => json_encode($parts, JSON_THROW_ON_ERROR)]);
        DB::table('health_checks')->where('checked_at', '<', now()->subDays(self::KEEP_DAYS))->delete();

        return ['ok' => $ok, 'parts' => $parts];
    }

    /** Uptime as a percentage over the last `$days` days, or null before the first check. */
    public static function uptime(int $days = 30): ?float
    {
        $row = DB::table('health_checks')->where('checked_at', '>=', CarbonImmutable::now()->subDays($days))
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN ok THEN 1 ELSE 0 END) as passed')->first();
        $total = is_object($row) && is_numeric($row->total ?? null) ? (int) $row->total : 0;
        $passed = is_object($row) && is_numeric($row->passed ?? null) ? (int) $row->passed : 0;

        return $total === 0 ? null : round($passed * 100 / $total, 2);
    }

    /** @return array{ok: bool, parts: array<string, array{ok: bool, ms: int}>, checked_at: string}|null */
    public static function latest(): ?array
    {
        $row = DB::table('health_checks')->latest('checked_at')->first();

        if (! is_object($row)) {
            return null;
        }

        $decoded = is_string($row->parts ?? null) ? json_decode($row->parts, true) : null;
        $parts = [];

        foreach (is_array($decoded) ? $decoded : [] as $name => $part) {
            if (is_string($name) && is_array($part)) {
                $parts[$name] = ['ok' => ($part['ok'] ?? false) === true, 'ms' => is_int($part['ms'] ?? null) ? $part['ms'] : 0];
            }
        }

        return [
            'ok' => (bool) ($row->ok ?? false),
            'parts' => $parts,
            'checked_at' => CarbonImmutable::parse(is_string($row->checked_at ?? null) ? $row->checked_at : 'now')->toIso8601String(),
        ];
    }

    /** @return array{ok: bool, ms: int} */
    private function time(callable $check): array
    {
        $started = hrtime(true);

        try {
            $check();
            $ok = true;
        } catch (Throwable) {
            $ok = false;
        }

        return ['ok' => $ok, 'ms' => (int) round((hrtime(true) - $started) / 1_000_000)];
    }
}
