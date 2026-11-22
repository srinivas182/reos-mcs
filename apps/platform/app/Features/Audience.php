<?php

declare(strict_types=1);

namespace App\Features;

use App\Authorization\Role;
use App\Enums\OrganisationType;
use App\Models\Organisation;
use App\Models\User;
use App\Tenancy\TenantFeature;
use Laravel\Pennant\Contracts\FeatureScopeSerializeable;

/**
 * Who a flag is checked for (A6.2): the tenant, its states and type, the
 * person's active role, and whether they are REOS staff. This is the Pennant
 * scope; it is stored as tenant and role (or person and role outside a
 * tenant), so a change to a flag or a tenant purges the stored answers.
 */
final readonly class Audience implements FeatureScopeSerializeable
{
    /**
     * @param  list<string>  $states
     * @param  array<string, mixed>  $tenantChoices  the tenant's own feature switches (screen 60)
     */
    public function __construct(
        public ?int $tenantId,
        public ?OrganisationType $tenantType,
        public array $states,
        public array $tenantChoices,
        public ?Role $role,
        public ?int $userId,
        public bool $internal,
    ) {}

    public static function tenant(Organisation $tenant): self
    {
        if ($tenant->type === OrganisationType::Platform) {
            return new self(null, null, [], [], null, null, true);
        }

        return new self($tenant->id, $tenant->type, $tenant->states ?? [], $tenant->features ?? [], null, null, false);
    }

    public static function person(User $user, ?Role $role, ?Organisation $tenant): self
    {
        $internal = $role === Role::SuperAdmin || $tenant?->type === OrganisationType::Platform;

        if ($tenant === null || $tenant->type === OrganisationType::Platform) {
            return new self(null, null, [], [], $role, $user->id, $internal);
        }

        return new self($tenant->id, $tenant->type, $tenant->states ?? [], $tenant->features ?? [], $role, $user->id, $internal);
    }

    /** The tenant's own switch for a tenant feature, if it made one. */
    public function choice(TenantFeature $feature): ?bool
    {
        $choice = $this->tenantChoices[$feature->value] ?? null;

        return is_bool($choice) && $feature->switchable() ? $choice : null;
    }

    /** What a percentage rollout counts: the tenant, else the person. */
    public function bucketKey(): string
    {
        return $this->tenantId !== null ? "t{$this->tenantId}" : 'u'.($this->userId ?? 0);
    }

    public function featureScopeSerialize(): string
    {
        $who = $this->tenantId !== null ? "t:{$this->tenantId}" : ($this->userId !== null ? "u:{$this->userId}" : 'platform');

        return $who.'|'.($this->role->value ?? '-').($this->internal ? '|i' : '');
    }
}
