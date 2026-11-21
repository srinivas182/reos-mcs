<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Domains\CustomHostnamesFactory;
use App\Domains\HostnameState;
use App\Models\Organisation;
use App\Models\TenantDomain;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Tenancy\Tenancy;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Tenants' own domains (A4.2b, screens 41 and 57): add one, show the DNS
 * records to add, check them (Verify DNS, and every 10 minutes while
 * waiting), and remove it. Live domains brand the sign-in page and serve
 * the app with automatic SSL.
 */
final readonly class TenantDomains
{
    public const AUDIT_SUBJECT = 'tenant_domain';

    private const CACHE_PREFIX = 'tenant-domain:';

    public function __construct(
        private CustomHostnamesFactory $provider,
        private AuditLogger $audit,
        private Tenancy $tenancy,
    ) {}

    public function of(Organisation $tenant): ?TenantDomain
    {
        return $this->tenancy->asPlatform(fn (): ?TenantDomain => TenantDomain::query()->where('organisation_id', $tenant->id)->first());
    }

    public function add(Organisation $tenant, string $hostname, User $actor): TenantDomain
    {
        $hostname = self::normalise($hostname);
        $base = config()->string('reos.tenancy.base_domain');

        if ($hostname === $base || str_ends_with($hostname, '.'.$base)) {
            throw ValidationException::withMessages(['hostname' => __('domains.errors.reos')]);
        }

        if ($this->of($tenant) !== null) {
            throw ValidationException::withMessages(['hostname' => __('domains.errors.one')]);
        }

        if ($this->tenancy->asPlatform(fn (): bool => TenantDomain::query()->where('hostname', $hostname)->exists())) {
            throw ValidationException::withMessages(['hostname' => __('domains.errors.taken')]);
        }

        $state = $this->call(fn () => $this->provider->make()->create($hostname));

        $domain = $this->tenancy->run($tenant->id, fn (): TenantDomain => TenantDomain::query()->create([
            'organisation_id' => $tenant->id,
            'hostname' => $hostname,
            'provider_id' => $state->providerId,
            'status' => $state->status,
            'ssl' => $state->ssl,
            'records' => $state->records,
            'last_error' => $state->error,
            'added_by' => $actor->id,
        ]));

        $this->audit->record('domain.added', self::AUDIT_SUBJECT, (string) $domain->id, ['hostname' => $hostname], $actor, $tenant->id);

        return $domain;
    }

    /** "Verify DNS", and the scheduled check while waiting. */
    public function check(TenantDomain $domain, ?User $actor = null): TenantDomain
    {
        $state = $this->call(fn () => $this->provider->make()->refresh($domain->provider_id, $domain->hostname));
        $wasLive = $domain->live();

        $this->tenancy->run($domain->organisation_id, function () use ($domain, $state): void {
            $domain->forceFill([
                'status' => $state->status,
                'ssl' => $state->ssl,
                'records' => $state->records,
                'last_error' => $state->error,
                'last_checked_at' => now(),
                'verified_at' => $state->live() ? ($domain->verified_at ?? now()) : null,
            ])->save();
        });

        Cache::forget(self::CACHE_PREFIX.$domain->hostname);

        if ($state->live() && ! $wasLive) {
            $this->audit->record('domain.verified', self::AUDIT_SUBJECT, (string) $domain->id, ['hostname' => $domain->hostname], $actor, $domain->organisation_id);
        }

        return $domain;
    }

    public function remove(TenantDomain $domain, User $actor): void
    {
        try {
            $this->provider->make()->delete($domain->provider_id);
        } catch (Throwable) {
            // Best effort: the provider forgets unused hostnames; REOS stops serving it either way.
        }

        Cache::forget(self::CACHE_PREFIX.$domain->hostname);
        $this->tenancy->run($domain->organisation_id, fn () => $domain->delete());
        $this->audit->record('domain.removed', self::AUDIT_SUBJECT, (string) $domain->id, ['hostname' => $domain->hostname], $actor, $domain->organisation_id);
    }

    /** Domains still waiting for DNS, checked every 10 minutes for up to 7 days. */
    public function checkWaiting(): int
    {
        $waiting = $this->tenancy->asPlatform(fn () => TenantDomain::query()
            ->where(fn ($q) => $q->where('status', '!=', HostnameState::ACTIVE)->orWhere('ssl', '!=', HostnameState::ACTIVE))
            ->where('created_at', '>', now()->subDays(7))
            ->get());

        foreach ($waiting as $domain) {
            try {
                $this->check($domain);
            } catch (Throwable) {
                // Shown on screen at the next manual check; try again next time.
            }
        }

        return $waiting->count();
    }

    /** The tenant a live custom domain belongs to (branded sign-in), cached for 10 minutes. */
    public function tenantFor(string $hostname): ?int
    {
        $id = Cache::remember(self::CACHE_PREFIX.mb_strtolower($hostname), 600, fn (): int => $this->tenancy->asPlatform(
            static function () use ($hostname): int {
                $found = TenantDomain::query()->where('hostname', mb_strtolower($hostname))
                    ->where('status', HostnameState::ACTIVE)->where('ssl', HostnameState::ACTIVE)->value('organisation_id');

                return is_numeric($found) ? (int) $found : 0;
            },
        ));

        return $id > 0 ? $id : null;
    }

    public static function normalise(string $hostname): string
    {
        $hostname = mb_strtolower(trim($hostname));
        $hostname = (string) preg_replace('#^https?://#', '', $hostname);

        return rtrim(explode('/', $hostname)[0], '.');
    }

    /**
     * @template T
     *
     * @param  \Closure(): T  $work
     * @return T
     */
    private function call(\Closure $work): mixed
    {
        try {
            return $work();
        } catch (Throwable $e) {
            report($e);

            throw ValidationException::withMessages(['hostname' => __('domains.errors.provider')]);
        }
    }
}
