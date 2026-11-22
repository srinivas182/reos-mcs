<?php

declare(strict_types=1);

namespace App\Notifications\Centre\Push;

use App\Models\PersonalAccessToken;
use App\Models\PushDevice;
use App\Models\User;

/**
 * The phones a person gets push on (B6.2b). A token belongs to the phone's
 * current sign-in: signing out deletes it, and a phone that signs in as
 * someone else moves its token to them.
 */
final class PushDevices
{
    /** An Expo push token, e.g. ExponentPushToken[xxxxxxxxxxxxxxxxxxxxxx]. */
    public const TOKEN = '/^Expo(nent)?PushToken\[[A-Za-z0-9_\-]{8,200}\]$/';

    public function register(User $user, string $token, string $platform, ?string $name, ?PersonalAccessToken $signIn): PushDevice
    {
        return PushDevice::query()->updateOrCreate(['token' => $token], [
            'user_id' => $user->id,
            'personal_access_token_id' => $signIn?->id,
            'platform' => $platform,
            'name' => $name,
        ]);
    }

    /** Turned off on the phone (or the person turned notifications off in iOS or Android settings). */
    public function forgetSignIn(User $user, PersonalAccessToken $signIn): void
    {
        PushDevice::query()->where('user_id', $user->id)->where('personal_access_token_id', $signIn->id)->delete();
    }

    public function count(User $user): int
    {
        return PushDevice::query()->where('user_id', $user->id)->count();
    }
}
