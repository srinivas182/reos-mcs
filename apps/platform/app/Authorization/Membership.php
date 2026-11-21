<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\Organisation;

/** One role a person holds, in one organisation. */
final readonly class Membership
{
    public function __construct(
        public Role $role,
        public Organisation $organisation,
    ) {}

    public function is(Role $role, int $organisationId): bool
    {
        return $this->role === $role && $this->organisation->id === $organisationId;
    }
}
