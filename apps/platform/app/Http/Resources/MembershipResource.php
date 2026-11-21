<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Authorization\Membership;
use App\Authorization\Role;
use App\Enums\OrganisationType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One role a person holds, in one organisation.
 *
 * @property Membership $resource
 */
final class MembershipResource extends JsonResource
{
    /** @var string|null */
    public static $wrap = null;

    /**
     * @return array{role: Role, organisation: array{id: int, name: string, type: OrganisationType}, admin: bool}
     */
    public function toArray(Request $request): array
    {
        $membership = $this->resource;

        return [
            'role' => $membership->role,
            'organisation' => [
                'id' => (int) $membership->organisation->id,
                'name' => (string) $membership->organisation->name,
                'type' => $membership->organisation->type,
            ],
            /** Admin roles need two-step before they grant anything. */
            'admin' => (bool) $membership->role->isAdmin(),
        ];
    }
}
