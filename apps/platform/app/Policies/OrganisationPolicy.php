<?php

declare(strict_types=1);

namespace App\Policies;

use App\Authorization\Permission;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Authorization\ActiveRole;
use App\Services\Authorization\Memberships;

/**
 * Organisations: members see their own; their admins (in that organisation,
 * using that role) manage it; `people.manage` covers all of them.
 */
final readonly class OrganisationPolicy
{
    public function __construct(private ActiveRole $active, private Memberships $memberships) {}

    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::PeopleManage->value);
    }

    public function view(User $actor, Organisation $organisation): bool
    {
        if ($actor->can(Permission::PeopleManage->value)) {
            return true;
        }

        foreach ($this->memberships->of($actor) as $membership) {
            if ($membership->organisation->id === $organisation->id) {
                return true;
            }
        }

        return false;
    }

    public function update(User $actor, Organisation $organisation): bool
    {
        if ($actor->can(Permission::PeopleManage->value)) {
            return true;
        }

        return $this->active->for($actor)?->organisation->id === $organisation->id
            && $actor->can(Permission::OrganisationManage->value);
    }
}
