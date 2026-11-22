<?php

declare(strict_types=1);

namespace App\Features;

use App\Authorization\Role;
use App\Models\FeatureFlag;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Authorization\ActiveRole;
use App\Tenancy\TenantFeature;
use Laravel\Pennant\Feature;

/**
 * Feature flags on Laravel Pennant (A6.2, ADR 0022). Pennant stores each
 * answer per audience; the answer comes from the flag's rollout here:
 *
 * 1. Turned off everywhere (kill switch) → off for everyone.
 * 2. Limited to some roles and the person's role isn't one → off.
 * 3. A tenant feature the tenant switched on or off itself (screen 60) → its choice.
 * 4. Inside the rollout (REOS staff → named tenants → states → a percentage
 *    of tenants → everyone) → on (a tenant feature: its type's default).
 * 5. Otherwise off.
 *
 * Every change to a flag or a tenant purges the stored answers.
 */
final class Flags
{
    /** @var array<string, FeatureFlag> */
    private array $flags = [];

    public function __construct(
        private readonly ActiveRole $roles,
        private readonly FlagUsage $usage,
    ) {}

    /** Registers every catalogue flag with Pennant (AppServiceProvider). */
    public static function define(): void
    {
        foreach (FlagCatalogue::keys() as $key) {
            Feature::define($key, static function (mixed $scope) use ($key): bool {
                $flags = app(self::class);

                return $scope instanceof Audience && $flags->resolve($flags->flag($key), $scope);
            });
        }
    }

    /** The flag's rollout row, created at its starting stage the first time. */
    public function flag(string $key): FeatureFlag
    {
        if (isset($this->flags[$key])) {
            return $this->flags[$key];
        }

        $declared = FlagCatalogue::FLAGS[$key] ?? null;

        if ($declared === null) {
            throw new \InvalidArgumentException("Unknown feature flag [{$key}].");
        }

        return $this->flags[$key] = FeatureFlag::query()->firstOrCreate(['key' => $key], [
            'name' => $declared['name'],
            'description' => $declared['description'],
            'owner' => $declared['owner'],
            'tenant_feature' => $declared['tenant'],
            'stage' => $declared['stage'],
            'stage_changed_at' => now(),
        ]);
    }

    /** @return list<FeatureFlag> every catalogue flag, in catalogue order */
    public function all(): array
    {
        return array_map($this->flag(...), FlagCatalogue::keys());
    }

    /** Whether the person has the flag in the role and tenant they're working in. */
    public function forUser(User $user, string $key): bool
    {
        $membership = $this->roles->for($user);

        return $this->check(Audience::person($user, $membership?->role, $membership?->organisation), $key);
    }

    public function forTenant(Organisation $tenant, string $key): bool
    {
        return $this->check(Audience::tenant($tenant), $key);
    }

    public function check(Audience $audience, string $key): bool
    {
        $on = Feature::for($audience)->active($key);

        if ($on) {
            $this->usage->used($key);
        }

        return $on;
    }

    /** The answer for one audience, from the flag's rollout (see the class comment). */
    public function resolve(FeatureFlag $flag, Audience $audience): bool
    {
        if ($flag->killed()) {
            return false;
        }

        if ($audience->role !== null && $flag->roles !== null && $flag->roles !== [] && ! in_array($audience->role->value, $flag->roles, true)) {
            return false;
        }

        $feature = $flag->tenant_feature ? TenantFeature::tryFrom($flag->key) : null;
        $choice = $feature !== null ? $audience->choice($feature) : null;

        if ($choice !== null) {
            return $choice;
        }

        if (! $this->inRollout($flag, $audience)) {
            return false;
        }

        // A tenant feature that has rolled out follows its type's default (beta ones are on once rolled out).
        if ($feature !== null && $audience->tenantType !== null && $feature->switchable()) {
            return $feature->defaultFor($audience->tenantType);
        }

        return true;
    }

    public function inRollout(FeatureFlag $flag, Audience $audience): bool
    {
        $stage = $flag->stage;

        return match (true) {
            $stage === RolloutStage::Off => false,
            $stage === RolloutStage::Everyone => true,
            $audience->internal => true,
            $stage->includes(RolloutStage::Tenants) && $audience->tenantId !== null && in_array($audience->tenantId, $flag->tenant_ids ?? [], true) => true,
            $stage->includes(RolloutStage::States) && array_intersect($audience->states, $flag->states ?? []) !== [] => true,
            $stage->includes(RolloutStage::Percent) => self::bucket($flag->key, $audience->bucketKey()) < $flag->percent,
            default => false,
        };
    }

    /** 0–99, the same for a tenant and flag every time. */
    public static function bucket(string $key, string $who): int
    {
        return crc32("{$key}:{$who}") % 100;
    }

    /** A flag's rollout changed: forget every stored answer for it. */
    public function changed(FeatureFlag $flag): void
    {
        unset($this->flags[$flag->key]);
        Feature::purge($flag->key);
        Feature::flushCache();
    }

    /** A tenant's features, states or type changed: forget the stored answers for every flag. */
    public function tenantChanged(): void
    {
        $this->flags = [];
        Feature::purge(FlagCatalogue::keys());
        Feature::flushCache();
    }

    /**
     * Roles a flag can be limited to (everyone but REOS staff, who always see internal flags).
     *
     * @return list<string>
     */
    public static function roles(): array
    {
        return array_map(static fn (Role $r): string => $r->value, array_filter(Role::cases(), static fn (Role $r): bool => $r !== Role::SuperAdmin));
    }
}
