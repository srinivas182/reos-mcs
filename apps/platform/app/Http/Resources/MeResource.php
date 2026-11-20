<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\SocialAccount;
use App\Models\User;
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
     * @return array{id: int, name: string|null, email: string|null, email_verified: bool, phone: string|null, has_password: bool, sign_in_methods: list<string>, locale: string, appearance: string, text_size: string}
     */
    public function toArray(Request $request): array
    {
        $user = $this->resource;

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
        ];
    }
}
