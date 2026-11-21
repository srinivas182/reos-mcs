<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Authorization\Role;
use App\Enums\OrganisationType;
use App\Models\Connection;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Tenancy\TenantPlan;
use App\Tenancy\TenantStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

/**
 * The super admin's view of every tenant (screens 59 and 60): the directory,
 * creating and configuring tenants, and their health. Every change is
 * audited with what it was before and after, tagged with the tenant.
 */
final readonly class Tenants
{
    public const AUDIT_SUBJECT = 'organisation';

    /** Roles that own a tenant of each type (the "owner" on screen 60). */
    private const OWNER_ROLES = [Role::AssociationAdmin, Role::Broker, Role::SchoolAdmin, Role::Provider];

    public function __construct(private AuditLogger $audit) {}

    /**
     * Every tenant (not REOS itself), with its member count.
     *
     * @param  array{type?: string|null, status?: string|null, state?: string|null, search?: string|null}  $filters
     * @return Collection<int, Organisation>
     */
    public function directory(array $filters = []): Collection
    {
        return Organisation::query()
            ->where('type', '!=', OrganisationType::Platform->value)
            ->with('parent:id,name')
            ->addSelect(['members_count' => self::membersCount()])
            ->when($filters['type'] ?? null, static fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['status'] ?? null, static fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['state'] ?? null, static fn (Builder $query, string $state) => $query->whereJsonContains('states', $state))
            ->when($filters['search'] ?? null, static function (Builder $query, string $search): void {
                $query->whereRaw('lower(name) like ?', ['%'.mb_strtolower($search).'%']);
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array{type: OrganisationType, name: string, parent_id?: int|null, states: list<string>, plan: string, seats?: int|null, trial_days?: int|null}  $data
     */
    public function create(array $data, User $actor): Organisation
    {
        return DB::transaction(function () use ($data, $actor): Organisation {
            $trialDays = $data['trial_days'] ?? null;

            $tenant = Organisation::query()->create([
                'type' => $data['type'],
                'name' => $data['name'],
                'parent_id' => $data['parent_id'] ?? null,
                'states' => $data['states'],
                'plan' => $data['plan'],
                'status' => $trialDays ? TenantStatus::Trial : TenantStatus::Active,
                'trial_ends_at' => $trialDays ? now()->addDays($trialDays)->endOfDay() : null,
                'limits' => array_key_exists('seats', $data) ? ['seats' => $data['seats']] : null,
            ]);

            $this->audit->record('tenant.created', self::AUDIT_SUBJECT, (string) $tenant->id, [
                'name' => $tenant->name, 'type' => $tenant->type->value, 'plan' => $data['plan'], 'states' => $data['states'],
            ], $actor, $tenant->id);

            return $tenant;
        });
    }

    /**
     * Plan, states, trial, features and limits. Only what changed is saved and audited.
     *
     * @param  array{plan?: string, states?: list<string>, trial_ends_at?: string|null, features?: array<string, bool>, limits?: array<string, mixed>}  $changes
     */
    public function update(Organisation $tenant, array $changes, User $actor): Organisation
    {
        return DB::transaction(function () use ($tenant, $changes, $actor): Organisation {
            $before = $this->snapshot($tenant);

            if (array_key_exists('plan', $changes)) {
                $tenant->plan = TenantPlan::from($changes['plan']);
            }
            if (array_key_exists('states', $changes)) {
                $tenant->states = $changes['states'];
            }
            if (array_key_exists('trial_ends_at', $changes)) {
                $tenant->trial_ends_at = $changes['trial_ends_at'] === null ? null : Carbon::parse($changes['trial_ends_at'])->endOfDay();
            }
            if (array_key_exists('features', $changes)) {
                $tenant->features = [...($tenant->features ?? []), ...$changes['features']];
            }
            if (array_key_exists('limits', $changes)) {
                $tenant->limits = [...($tenant->limits ?? []), ...$changes['limits']];
            }

            $tenant->save();
            $after = $this->snapshot($tenant);
            $changed = array_keys(array_filter($after, static fn (mixed $value, string $key): bool => $before[$key] !== $value, ARRAY_FILTER_USE_BOTH));

            if ($changed !== []) {
                $this->audit->record('tenant.updated', self::AUDIT_SUBJECT, (string) $tenant->id, [
                    'changed' => $changed,
                    'before' => array_intersect_key($before, array_flip($changed)),
                    'after' => array_intersect_key($after, array_flip($changed)),
                ], $actor, $tenant->id);
            }

            return $tenant;
        });
    }

    /** Suspend, archive, restore or put on a trial; suspending and archiving need a reason. */
    public function setStatus(Organisation $tenant, TenantStatus $status, ?string $reason, User $actor): Organisation
    {
        $from = $tenant->status;
        $tenant->forceFill([
            'status' => $status,
            'status_reason' => in_array($status, [TenantStatus::Suspended, TenantStatus::Archived], true) ? $reason : null,
        ])->save();

        $this->audit->record('tenant.status_changed', self::AUDIT_SUBJECT, (string) $tenant->id, [
            'from' => $from->value, 'to' => $status->value, 'reason' => $reason,
        ], $actor, $tenant->id);

        return $tenant;
    }

    /**
     * The health card on screen 60: owner, members against seats, last
     * activity and connections that need attention.
     *
     * @return array{owner: User|null, members: int, last_active_at: Carbon|null, connection_problems: int}
     */
    public function health(Organisation $tenant): array
    {
        $memberIds = self::memberIds($tenant->id);
        $lastSeen = DB::table('known_devices')->whereIn('user_id', $memberIds)->max('last_seen_at');

        return [
            'owner' => $this->owner($tenant),
            'members' => $memberIds->count(),
            'last_active_at' => is_string($lastSeen) ? Carbon::parse($lastSeen) : null,
            'connection_problems' => Connection::query()
                ->where('owner_type', Connection::OWNER_ORGANISATION)
                ->where('owner_id', $tenant->id)
                ->where('status', '!=', 'active')
                ->count(),
        ];
    }

    /** The first person holding the tenant's admin role. */
    public function owner(Organisation $tenant): ?User
    {
        $roleIds = DB::table('roles')->whereIn('name', array_map(static fn (Role $role): string => $role->value, self::OWNER_ROLES))->pluck('id');
        $id = DB::table('model_has_roles')
            ->where('organisation_id', $tenant->id)
            ->where('model_type', (new User)->getMorphClass())
            ->whereIn('role_id', $roleIds)
            ->orderBy('model_id')
            ->value('model_id');

        return is_numeric($id) ? User::query()->find((int) $id) : null;
    }

    /** @return SupportCollection<int, int> */
    private static function memberIds(int $organisationId): SupportCollection
    {
        return DB::table('model_has_roles')
            ->where('organisation_id', $organisationId)
            ->where('model_type', (new User)->getMorphClass())
            ->distinct()
            ->pluck('model_id')
            ->map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0);
    }

    private static function membersCount(): QueryBuilder
    {
        return DB::table('model_has_roles')
            ->selectRaw('count(distinct model_id)')
            ->whereColumn('model_has_roles.organisation_id', 'organisations.id')
            ->where('model_type', (new User)->getMorphClass());
    }

    /** @return array<string, mixed> What an audit entry compares. */
    private function snapshot(Organisation $tenant): array
    {
        return [
            'plan' => $tenant->plan?->value,
            'states' => $tenant->states ?? [],
            'trial_ends_at' => $tenant->trial_ends_at?->toDateString(),
            'features' => $tenant->features ?? [],
            'limits' => $tenant->limits ?? [],
        ];
    }
}
