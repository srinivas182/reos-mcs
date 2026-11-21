<?php

declare(strict_types=1);

namespace App\Tenancy;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

/**
 * Whose data this request or job may touch (ADR 0015).
 *
 * Three states:
 * - a tenant (an organisation id): only that tenant's rows are visible;
 * - platform: everything (REOS super admins, webhooks, schedulers, seeders);
 * - none: no tenant rows at all (signed-out visitors, people with no
 *   organisation). This is also what the database falls back to, so a lost
 *   context fails closed.
 *
 * The state is enforced twice: by PostgreSQL row-level security, which reads
 * the session settings `reos.tenant` and `reos.scope`, and by the
 * `BelongsToTenant` Eloquent scope.
 *
 * One instance per request (and per Octane request); the database session
 * is reset at the start of each request and job.
 */
final class Tenancy
{
    public const SETTING_TENANT = 'reos.tenant';

    public const SETTING_SCOPE = 'reos.scope';

    public const SCOPE_PLATFORM = 'platform';

    private ?int $tenant = null;

    private bool $platform;

    public function __construct(bool $platform = false)
    {
        $this->platform = $platform;
    }

    /** The current tenant's organisation id, or null for platform or none. */
    public function tenant(): ?int
    {
        return $this->platform ? null : $this->tenant;
    }

    public function isPlatform(): bool
    {
        return $this->platform;
    }

    /** Neither a tenant nor platform: no tenant rows are visible. */
    public function isNone(): bool
    {
        return ! $this->platform && $this->tenant === null;
    }

    public function enter(int $organisationId): void
    {
        $this->tenant = $organisationId;
        $this->platform = false;
        $this->apply();
    }

    public function enterPlatform(): void
    {
        $this->tenant = null;
        $this->platform = true;
        $this->apply();
    }

    public function enterNone(): void
    {
        $this->tenant = null;
        $this->platform = false;
        $this->apply();
    }

    /**
     * Run work as one tenant (or as platform when null), then put back what
     * was there before.
     *
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    public function run(?int $organisationId, Closure $work): mixed
    {
        [$tenant, $platform] = [$this->tenant, $this->platform];
        $organisationId === null ? $this->enterPlatform() : $this->enter($organisationId);

        try {
            return $work();
        } finally {
            $this->tenant = $tenant;
            $this->platform = $platform;
            $this->apply();
        }
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    public function asPlatform(Closure $work): mixed
    {
        return $this->run(null, $work);
    }

    /** @return array{0: int|null, 1: bool} The state, to put back later. */
    public function snapshot(): array
    {
        return [$this->tenant, $this->platform];
    }

    /** @param  array{0: int|null, 1: bool}  $state */
    public function restore(array $state): void
    {
        [$this->tenant, $this->platform] = $state;
        $this->apply();
    }

    /** What a queued job should run as: a tenant id, `platform` or `none`. */
    public function forJob(): string
    {
        return $this->platform ? self::SCOPE_PLATFORM : ($this->tenant === null ? 'none' : (string) $this->tenant);
    }

    /** Enter what `forJob()` recorded. Jobs queued before tenancy existed run as platform. */
    public function enterJob(?string $recorded): void
    {
        match (true) {
            $recorded === null, $recorded === self::SCOPE_PLATFORM => $this->enterPlatform(),
            $recorded === 'none' => $this->enterNone(),
            default => $this->enter((int) $recorded),
        };
    }

    /** A cache key that can't collide with another tenant's. */
    public function cacheKey(string $key): string
    {
        return ($this->platform ? 'platform' : 't'.($this->tenant ?? 'none')).':'.$key;
    }

    /** Where this tenant's files live in storage. */
    public function path(string $path = ''): string
    {
        $base = $this->platform || $this->tenant === null ? 'platform' : 'tenants/'.$this->tenant;

        return rtrim($base.'/'.ltrim($path, '/'), '/');
    }

    /** Write the state into a database session (PostgreSQL only). */
    public function applyTo(Connection $connection): void
    {
        if ($connection->getDriverName() !== 'pgsql') {
            return;
        }

        $connection->select('select set_config(?, ?, false), set_config(?, ?, false)', [
            self::SETTING_TENANT, $this->tenant === null || $this->platform ? '' : (string) $this->tenant,
            self::SETTING_SCOPE, $this->platform ? self::SCOPE_PLATFORM : '',
        ]);
    }

    private function apply(): void
    {
        $this->applyTo(DB::connection());
    }
}
