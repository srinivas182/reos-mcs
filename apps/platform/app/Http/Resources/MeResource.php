<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Onboarding\OnboardingResource;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Authorization\ActiveRole;
use App\Services\Authorization\Memberships;
use App\Services\States\PersonStates;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in person, as `GET /api/v1/me` returns it.
 *
 * @property User $resource
 */
final class MeResource extends JsonResource
{
    /** @var string|null */
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->resource;
        $active = app(ActiveRole::class);
        $current = $active->for($user);

        return [
            'id' => $user->id,
            /** Null until the person gives it (phone sign-ups give it in setup). */
            'name' => $user->name,
            'email' => $user->email,
            'email_verified' => $user->email !== null && $user->email_verified_at !== null,
            'phone' => $user->phone,
            'has_password' => $user->password !== null,
            /**
             * How this person can sign in.
             *
             * @var list<string>
             */
            'sign_in_methods' => array_values(array_unique(array_filter([
                $user->password !== null ? 'password' : null,
                $user->phone_verified_at !== null ? 'phone' : null,
                ...$user->socialAccounts->map(static fn (SocialAccount $account): string => $account->provider->value)->all(),
            ]))),
            'locale' => $user->locale->value,
            'appearance' => $user->appearance->value,
            'text_size' => $user->text_size->value,
            /**
             * Every role the person holds, each in an organisation. Admin
             * roles need two-step before they grant anything.
             */
            'roles' => MembershipResource::collection(app(Memberships::class)->of($user)),
            /** The role in use on this device; null for someone with no role yet. */
            'active_role' => $current === null ? null : [
                'role' => $current->role,
                'organisation_id' => (int) $current->organisation->id,
            ],
            /**
             * What the active role allows, such as `learning.study`.
             *
             * @var list<string>
             */
            'permissions' => $active->permissions($user),
            /**
             * The live states the person works in and the one in use (A5.1).
             * The switcher shows when there is more than one option.
             *
             * @var array{current: array{code: string, name: string}, options: list<array{code: string, name: string}>}
             */
            'states' => app(PersonStates::class)->switcher($user),
            /** Path, target state and setup choices from getting started (A3.3). */
            'onboarding' => new OnboardingResource($user),
            'two_step' => [
                /** An authenticator app or a passkey is set up. */
                'enabled' => (bool) ActiveRole::hasTwoStep($user),
                /** The active role is an admin role that needs two-step first. */
                'required' => (bool) $active->needsTwoStep($user),
            ],
        ];
    }
}
