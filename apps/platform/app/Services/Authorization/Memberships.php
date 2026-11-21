<?php

declare(strict_types=1);

namespace App\Services\Authorization;

use App\Authorization\Membership;
use App\Authorization\Role;
use App\Authorization\RoleRefused;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Tenancy\TenantStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * The roles people hold and in which organisation. Granting and removing a
 * role is audited; the platform always keeps at least one super admin.
 */
final readonly class Memberships
{
    public const AUDIT_SUBJECT = 'user';

    public function __construct(
        private AuditLogger $audit,
        private PermissionRegistrar $registrar,
    ) {}

    /**
     * Every role the person holds, most responsibility first (Role::rank),
     * then by organisation name. Roles in a suspended or archived tenant
     * don't count until it's restored (A4.1b).
     *
     * @return list<Membership>
     */
    public function of(User $user): array
    {
        /** @var Collection<int, object{name: string, organisation_id: int}> $rows */
        $rows = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', $user->getMorphClass())
            ->where('model_has_roles.model_id', $user->id)
            ->get(['roles.name', 'model_has_roles.organisation_id']);

        $organisations = Organisation::query()
            ->whereIn('id', $rows->pluck('organisation_id')->unique()->all())
            ->whereNotIn('status', [TenantStatus::Suspended->value, TenantStatus::Archived->value])
            ->get()
            ->keyBy('id');

        return array_values($rows
            ->map(static function (object $row) use ($organisations): ?Membership {
                $role = Role::tryFrom($row->name);
                $organisation = $organisations->get($row->organisation_id);

                return $role !== null && $organisation instanceof Organisation ? new Membership($role, $organisation) : null;
            })
            ->filter()
            ->sortBy(static fn (Membership $m): string => sprintf('%02d %s', $m->role->rank(), mb_strtolower($m->organisation->name)))
            ->all());
    }

    public function find(User $user, Role $role, int $organisationId): ?Membership
    {
        foreach ($this->of($user) as $membership) {
            if ($membership->is($role, $organisationId)) {
                return $membership;
            }
        }

        return null;
    }

    /**
     * People who hold a role in any organisation.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    public function holders(Role $role): \Illuminate\Database\Eloquent\Collection
    {
        return User::query()
            ->whereIn('id', $this->holderIds($role))
            ->get();
    }

    /** Give someone a role, audited (the People screen and invitations). */
    public function grant(User $user, Role $role, Organisation $organisation, ?User $actor = null): Membership
    {
        $membership = $this->attach($user, $role, $organisation);

        $this->audit->record('role.granted', self::AUDIT_SUBJECT, (string) $user->id, [
            'role' => $role->value,
            'organisation_id' => $organisation->id,
        ], $actor);

        return $membership;
    }

    /** Give someone a role without an audit entry: demo data and test factories only. */
    public function attach(User $user, Role $role, Organisation $organisation): Membership
    {
        if (! in_array($organisation->type, $role->organisationTypes(), true)) {
            throw new RoleRefused('roles.errors.wrongOrganisation');
        }

        $this->withOrganisation($organisation, static fn () => $user->assignRole($role->value));

        return new Membership($role, $organisation);
    }

    public function revoke(User $user, Role $role, Organisation $organisation, ?User $actor = null): void
    {
        if ($this->find($user, $role, $organisation->id) === null) {
            throw RoleRefused::notHeld();
        }

        if ($role === Role::SuperAdmin && $this->holderIds($role)->count() <= 1) {
            throw new RoleRefused('roles.errors.lastSuperAdmin');
        }

        $this->withOrganisation($organisation, static fn () => $user->removeRole($role->value));

        $this->audit->record('role.removed', self::AUDIT_SUBJECT, (string) $user->id, [
            'role' => $role->value,
            'organisation_id' => $organisation->id,
        ], $actor);
    }

    /** @return Collection<int, int> */
    private function holderIds(Role $role): Collection
    {
        /** @var Collection<int, int> */
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', $role->value)
            ->where('model_has_roles.model_type', (new User)->getMorphClass())
            ->distinct()
            ->pluck('model_has_roles.model_id')
            ->map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0);
    }

    /** Spatie assigns roles in the "current team"; set it for one call. */
    private function withOrganisation(Organisation $organisation, callable $callback): void
    {
        $previous = $this->registrar->getPermissionsTeamId();
        $this->registrar->setPermissionsTeamId($organisation->id);

        try {
            $callback();
        } finally {
            $this->registrar->setPermissionsTeamId($previous);
        }
    }
}
