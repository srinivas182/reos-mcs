<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Returned when sign-in succeeds on the phone.
 *
 * @property-read array{user: User, token: string, new_device: bool, created: bool} $resource
 */
final class SignInResource extends JsonResource
{
    /** @var string|null */
    public static $wrap = null;

    /**
     * @return array{token: string, token_type: 'Bearer', new_device: bool, new_account: bool, user: MeResource}
     */
    public function toArray(Request $request): array
    {
        return [
            /** Bearer token for this device. Keep it in secure storage. */
            'token' => $this->resource['token'],
            'token_type' => 'Bearer',
            /** True when this person had never signed in on this device before. */
            'new_device' => (bool) $this->resource['new_device'],
            /** True when this sign-in created the account (show the 60-second setup). */
            'new_account' => (bool) $this->resource['created'],
            'user' => new MeResource($this->resource['user']),
        ];
    }
}
