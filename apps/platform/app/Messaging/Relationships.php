<?php

declare(strict_types=1);

namespace App\Messaging;

use App\Authorization\Membership;
use App\Authorization\Role;
use App\Enums\OrganisationType;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Authorization\ActiveRole;
use App\Services\Authorization\Memberships;
use Illuminate\Support\Facades\DB;

/**
 * Who can message whom (B6.1, ADR 0023): people with a working relationship
 * in the same organisation, its parent or its teams. Brokers with their
 * agents, mentors and other brokers; agents with each other only inside a
 * team; school admins and instructors with their students and each other;
 * mentors with their mentees (agents or students); association admins with
 * each other (members get announcements). Providers join once an agent
 * accepts their request (provider app). REOS support works as the tenant's
 * admin only during a support session the owner approved (A4.1c).
 */
final readonly class Relationships
{
    /** Pairs of roles that may talk, either way round. */
    private const PAIRS = [
        ['broker', 'agent'], ['broker', 'broker'], ['broker', 'mentor'], ['mentor', 'agent'],
        // Only inside a team (checked below).
        ['agent', 'agent'],
        ['school_admin', 'instructor'], ['school_admin', 'candidate'], ['school_admin', 'school_admin'],
        ['instructor', 'candidate'], ['instructor', 'instructor'],
        ['mentor', 'candidate'], ['mentor', 'school_admin'], ['mentor', 'instructor'],
        ['association_admin', 'association_admin'],
    ];

    public function __construct(
        private Memberships $memberships,
        private ActiveRole $roles,
    ) {}

    /**
     * Everyone the person may message, with the role and organisation that
     * connects them, ordered by name.
     *
     * @return list<array{user: User, role: Role, organisation: string}>
     */
    public function contacts(User $user): array
    {
        $found = [];

        foreach ($this->senderMemberships($user) as $membership) {
            $partners = self::partners($membership->role);

            if ($partners === []) {
                continue;
            }

            $family = self::family($membership->organisation);
            $rows = DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->join('organisations', 'organisations.id', '=', 'model_has_roles.organisation_id')
                ->where('model_has_roles.model_type', $user->getMorphClass())
                ->whereIn('model_has_roles.organisation_id', $family)
                ->whereIn('roles.name', $partners)
                ->where('model_has_roles.model_id', '!=', $user->id)
                ->whereNotIn('organisations.status', ['suspended', 'archived'])
                ->get(['model_has_roles.model_id', 'roles.name', 'model_has_roles.organisation_id', 'organisations.name as organisation', 'organisations.type']);

            foreach ($rows as $row) {
                $role = Role::tryFrom(is_string($row->name) ? $row->name : '');
                $id = is_numeric($row->model_id) ? (int) $row->model_id : 0;

                if ($role === null || $id === 0 || isset($found[$id])) {
                    continue;
                }

                // Agents talk to other agents only inside their own team.
                if ($membership->role === Role::Agent && $role === Role::Agent
                    && ((int) $row->organisation_id !== $membership->organisation->id || $membership->organisation->type !== OrganisationType::Team)) {
                    continue;
                }

                $found[$id] = ['role' => $role, 'organisation' => is_string($row->organisation) ? $row->organisation : ''];
            }
        }

        // Blocked either way: not offered (B6.1b).
        foreach (app(Safety::class)->blockedWith($user->id) as $id) {
            unset($found[$id]);
        }

        if ($found === []) {
            return [];
        }

        $users = User::query()->whereIn('id', array_keys($found))->get()->sortBy(static fn (User $u): string => mb_strtolower($u->name ?? $u->email ?? ''));

        return array_values($users->map(static fn (User $u): array => ['user' => $u, ...$found[$u->id]])->all());
    }

    public function canMessage(User $from, User $to): bool
    {
        if ($from->id === $to->id) {
            return false;
        }

        foreach ($this->contacts($from) as $contact) {
            if ($contact['user']->id === $to->id) {
                return true;
            }
        }

        return false;
    }

    /**
     * The roles the person holds, plus the tenant admin role REOS support
     * works in during an approved support session.
     *
     * @return list<Membership>
     */
    private function senderMemberships(User $user): array
    {
        $held = $this->memberships->of($user);
        $active = $this->roles->for($user);

        if ($active !== null && array_filter($held, static fn (Membership $m): bool => $m->is($active->role, $active->organisation->id)) === []) {
            $held[] = $active;
        }

        return $held;
    }

    /** @return list<string> */
    private static function partners(Role $role): array
    {
        $partners = [];

        foreach (self::PAIRS as [$a, $b]) {
            if ($a === $role->value) {
                $partners[] = $b;
            }

            if ($b === $role->value) {
                $partners[] = $a;
            }
        }

        return array_values(array_unique($partners));
    }

    /**
     * The organisation, its parent and its teams.
     *
     * @return list<int>
     */
    private static function family(Organisation $organisation): array
    {
        if ($organisation->type === OrganisationType::Platform) {
            return [];
        }

        $root = $organisation->parent_id ?? $organisation->id;
        $ids = Organisation::query()->where('parent_id', $root)->pluck('id')->map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0)->all();

        return array_values(array_unique([$root, $organisation->id, ...$ids]));
    }
}
