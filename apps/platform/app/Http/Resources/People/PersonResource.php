<?php

declare(strict_types=1);

namespace App\Http\Resources\People;

use App\Authorization\Membership;
use App\Models\User;
use App\Services\Authorization\ActiveRole;
use App\Services\Authorization\Memberships;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A person on the People screen: who they are and the roles they hold.
 *
 * @property User $resource
 */
final class PersonResource extends JsonResource
{
    /**
     * @return array{id: int, name: string|null, email: string|null, phone: string|null, joined_at: string|null, two_step: bool, roles: list<array{role: string, organisation: array{id: int, name: string, type: string}, admin: bool}>}
     */
    public function toArray(Request $request): array
    {
        $user = $this->resource;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'joined_at' => $user->created_at?->toIso8601String(),
            'two_step' => ActiveRole::hasTwoStep($user),
            'roles' => array_map(static fn (Membership $m): array => [
                'role' => $m->role->value,
                'organisation' => [
                    'id' => $m->organisation->id,
                    'name' => $m->organisation->name,
                    'type' => $m->organisation->type->value,
                ],
                'admin' => $m->role->isAdmin(),
            ], app(Memberships::class)->of($user)),
        ];
    }
}
