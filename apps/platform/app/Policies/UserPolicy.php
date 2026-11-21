<?php

declare(strict_types=1);

namespace App\Policies;

use App\Authorization\Permission;
use App\Models\User;

/** People: anyone sees themselves; the People screen needs `people.manage`. */
final class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::PeopleManage->value);
    }

    public function view(User $actor, User $person): bool
    {
        return $actor->is($person) || $actor->can(Permission::PeopleManage->value);
    }

    /** Grant or remove roles. */
    public function manageRoles(User $actor, User $person): bool
    {
        return $actor->can(Permission::PeopleManage->value);
    }
}
