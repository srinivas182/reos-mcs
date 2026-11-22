<?php

declare(strict_types=1);

namespace App\Features;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Which flags were on during this request, and whether it failed (A6.2):
 * the error rate the automatic rollback watches. Counted in the cache per
 * minute (one use per flag per request); `reos:flags:watch` saves them.
 * Bound per request (scoped), so Octane starts each request empty.
 */
final class FlagUsage
{
    private const TTL_MINUTES = 30;

    /** @var array<string, true> */
    private array $active = [];

    private bool $failed = false;

    public function __construct(private readonly Cache $cache) {}

    public function used(string $key): void
    {
        if (isset($this->active[$key])) {
            return;
        }

        $this->active[$key] = true;
        $this->bump($key, 'uses');

        // A flag first checked after the request already failed still counts the error.
        if ($this->failed) {
            $this->bump($key, 'errors');
        }
    }

    /** Called by the exception reporter: every flag on in this request gets the error. */
    public function failed(): void
    {
        if ($this->failed) {
            return;
        }

        $this->failed = true;

        foreach (array_keys($this->active) as $key) {
            $this->bump($key, 'errors');
        }
    }

    public function read(string $key, CarbonImmutable $minute, string $field): int
    {
        $value = $this->cache->get(self::cacheKey($key, $minute->getTimestamp(), $field));

        return is_numeric($value) ? (int) $value : 0;
    }

    private function bump(string $key, string $field): void
    {
        $cacheKey = self::cacheKey($key, CarbonImmutable::now()->startOfMinute()->getTimestamp(), $field);
        $this->cache->add($cacheKey, 0, now()->addMinutes(self::TTL_MINUTES));
        $this->cache->increment($cacheKey);
    }

    private static function cacheKey(string $key, int $minute, string $field): string
    {
        return "flags:{$minute}:{$key}:{$field}";
    }
}
