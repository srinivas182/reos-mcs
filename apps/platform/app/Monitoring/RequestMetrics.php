<?php

declare(strict_types=1);

namespace App\Monitoring;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\DB;

/**
 * Request counts, failures and durations per minute (A6.1; screen 58's
 * latency and traffic). Each request adds to counters in the cache (cheap,
 * no database write per request); `reos:metrics:flush` copies finished
 * minutes into `request_metrics` every minute. Durations go into fixed
 * buckets, so p95 is read from the histogram without storing every request.
 */
final readonly class RequestMetrics
{
    /** Upper bounds (ms) of the histogram buckets; the last bucket is everything slower. */
    public const BUCKETS = [50, 100, 200, 300, 500, 1000, 2000];

    private const TTL_MINUTES = 15;

    public function __construct(private Cache $cache) {}

    public function record(float $milliseconds, int $status, ?CarbonImmutable $at = null): void
    {
        $minute = ($at ?? CarbonImmutable::now())->startOfMinute()->getTimestamp();
        $bucket = 'over_2000';

        foreach (self::BUCKETS as $bound) {
            if ($milliseconds < $bound) {
                $bucket = "under_{$bound}";

                break;
            }
        }

        $this->bump($minute, 'requests');
        $this->bump($minute, $bucket);

        if ($status >= 500) {
            $this->bump($minute, 'errors');
        }
    }

    /** Copies the last few finished minutes from the cache into the table (safe to run twice). */
    public function flush(?CarbonImmutable $now = null): int
    {
        $now = ($now ?? CarbonImmutable::now())->startOfMinute();
        $written = 0;

        for ($back = 1; $back <= 10; $back++) {
            $minute = $now->subMinutes($back);
            $row = ['requests' => $this->read($minute->getTimestamp(), 'requests')];

            if ($row['requests'] === 0) {
                continue;
            }

            $row['errors'] = $this->read($minute->getTimestamp(), 'errors');

            foreach (self::BUCKETS as $bound) {
                $row["under_{$bound}"] = $this->read($minute->getTimestamp(), "under_{$bound}");
            }

            $row['over_2000'] = $this->read($minute->getTimestamp(), 'over_2000');
            DB::table('request_metrics')->upsert([['minute' => $minute->toDateTimeString(), ...$row]], ['minute'], array_keys($row));
            $written++;
        }

        return $written;
    }

    /**
     * p95 (ms, the bucket's upper bound) from a histogram row or a sum of rows.
     *
     * @param  array<string, int>  $histogram
     */
    public static function p95(array $histogram): ?int
    {
        $total = $histogram['requests'] ?? 0;

        if ($total === 0) {
            return null;
        }

        $seen = 0;

        foreach (self::BUCKETS as $bound) {
            $seen += $histogram["under_{$bound}"] ?? 0;

            if ($seen >= 0.95 * $total) {
                return $bound;
            }
        }

        return 2000;
    }

    private function bump(int $minute, string $field): void
    {
        $key = "metrics:{$minute}:{$field}";
        $this->cache->add($key, 0, now()->addMinutes(self::TTL_MINUTES));
        $this->cache->increment($key);
    }

    private function read(int $minute, string $field): int
    {
        $value = $this->cache->get("metrics:{$minute}:{$field}");

        return is_numeric($value) ? (int) $value : 0;
    }
}
