<?php

declare(strict_types=1);

namespace App\Brokerage;

use App\Authorization\Role;
use App\Enums\OrganisationType;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Authorization\ActiveRole;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The brokerage a broker is working in (A15.1b): the broker role held at the
 * brokerage itself. A team leader (the broker role on a team) runs their
 * team, not the brokerage's roster or seats.
 */
final readonly class BrokerageAccess
{
    public function __construct(private ActiveRole $roles) {}

    public function brokerage(User $user): Organisation
    {
        $membership = $this->roles->for($user);

        if ($membership === null || $membership->role !== Role::Broker || $membership->organisation->type !== OrganisationType::Brokerage) {
            throw new NotFoundHttpException;
        }

        return $membership->organisation;
    }
}
