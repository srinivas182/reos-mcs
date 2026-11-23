<?php

declare(strict_types=1);

namespace App\Deals;

use App\Authorization\Role;
use App\Models\Deal;
use App\Models\User;
use App\Services\Authorization\ActiveRole;

/** Deals are agents' and brokers' own (B9.1): each sees only the deals that are theirs. */
final readonly class DealAccess
{
    public function __construct(private ActiveRole $roles) {}

    public function allow(User $user): void
    {
        abort_unless(in_array($this->roles->for($user)?->role, [Role::Agent, Role::Broker], true), 404);
    }

    public function find(User $user, string $uuid): Deal
    {
        $this->allow($user);

        return Deal::query()->where('user_id', $user->id)->where('uuid', $uuid)->with('dates')->firstOrFail();
    }
}
