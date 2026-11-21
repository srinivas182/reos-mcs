<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organisation\Concerns;

use App\Enums\OrganisationType;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Authorization\ActiveRole;

/** The organisation of the role in use, for tenant admin screens; 404 for REOS staff and people without one. */
trait ForAdminOrganisation
{
    /** @param  list<OrganisationType>  $types */
    private function adminOrganisation(User $user, array $types = []): Organisation
    {
        $membership = app(ActiveRole::class)->for($user);
        $organisation = $membership?->organisation;

        abort_if($organisation === null || $organisation->type === OrganisationType::Platform, 404);
        abort_if($types !== [] && ! in_array($organisation->type, $types, true), 404);

        return $organisation;
    }
}
