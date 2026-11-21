<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Authorization\Role;
use App\Enums\OrganisationType;
use App\Models\Invitation;
use App\Models\Organisation;
use App\Models\User;
use App\Tenancy\Tenancy;
use Illuminate\Support\Facades\DB;

/**
 * Seats (A4.3a): how many people a tenant may have in its seat role, and who
 * pays (the tenant's limits, set by REOS on Tenant detail, screen 60).
 *
 * - The seat role is the one people pay for: agents in a brokerage (its
 *   teams included), instructors in a school, providers in a provider
 *   business. Admin roles (broker, school admin, association admin) and
 *   mentors never take a seat; associations have no seat limit.
 * - Seats in use count the people who hold the role plus open invites for
 *   it, so invites stop at the limit instead of failing when accepted.
 */
final readonly class Seats
{
    public function __construct(private Tenancy $tenancy) {}

    /** The tenant whose seats a role in this organisation uses (a team uses its brokerage's). */
    public function owner(Organisation $organisation): Organisation
    {
        return $organisation->type === OrganisationType::Team && $organisation->parent !== null ? $organisation->parent : $organisation;
    }

    public function role(Organisation $owner): ?Role
    {
        return match ($owner->type) {
            OrganisationType::Brokerage => Role::Agent,
            OrganisationType::School => Role::Instructor,
            OrganisationType::ProviderBusiness => Role::Provider,
            default => null,
        };
    }

    /** @return array{role: Role|null, limit: int|null, used: int, pending: int, left: int|null, payer: string} */
    public function usage(Organisation $organisation): array
    {
        $owner = $this->owner($organisation);
        $role = $this->role($owner);
        $limits = $owner->tenantLimits();
        $limit = $role === null ? null : $limits->seats;
        [$used, $pending] = $role === null ? [0, 0] : $this->count($owner, $role);

        return [
            'role' => $role,
            'limit' => $limit,
            'used' => $used,
            'pending' => $pending,
            'left' => $limit === null ? null : max(0, $limit - $used - $pending),
            'payer' => $limits->payer,
        ];
    }

    /** Whether giving this role here takes a seat. */
    public function takesSeat(Organisation $organisation, Role $role): bool
    {
        return $this->role($this->owner($organisation)) === $role;
    }

    /** Whether this person already holds the role somewhere in the tenant (no second seat). */
    public function holds(User $user, Organisation $organisation, Role $role): bool
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', $role->value)
            ->where('model_has_roles.model_type', $user->getMorphClass())
            ->where('model_has_roles.model_id', $user->id)
            ->whereIn('model_has_roles.organisation_id', $this->subtree($this->owner($organisation)))
            ->exists();
    }

    /** @return array{0: int, 1: int} people holding the role, open invites for it */
    private function count(Organisation $owner, Role $role): array
    {
        $ids = $this->subtree($owner);
        $used = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', $role->value)
            ->where('model_has_roles.model_type', (new User)->getMorphClass())
            ->whereIn('model_has_roles.organisation_id', $ids)
            ->distinct()
            ->count('model_has_roles.model_id');
        $pending = $this->tenancy->run($owner->id, static fn (): int => Invitation::query()
            ->where('organisation_id', $owner->id)
            ->where('role', $role->value)
            ->where('status', Invitation::PENDING)
            ->where('expires_at', '>', now())
            ->count());

        return [$used, $pending];
    }

    /** @return list<int> the tenant and its teams */
    public function subtree(Organisation $owner): array
    {
        $teams = Organisation::query()->where('parent_id', $owner->id)->where('type', OrganisationType::Team->value)->pluck('id')
            ->map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0)->all();

        return array_values([$owner->id, ...$teams]);
    }
}
