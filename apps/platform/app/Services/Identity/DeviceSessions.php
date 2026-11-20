<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Identity\DeviceInfo;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Notifications\NewDeviceSignIn;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One token per installed app. Signing in again on the same phone replaces
 * its token; a phone the person has never used before triggers an email.
 */
final readonly class DeviceSessions
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @return array{token: string, new_device: bool}
     */
    public function issue(User $user, DeviceInfo $device, string $method, bool $newAccount): array
    {
        [$token, $newDevice] = DB::transaction(function () use ($user, $device): array {
            $user->tokens()->where('device_id', $device->id)->delete();

            $known = DB::table('known_devices')->where('user_id', $user->id)->where('device_id', $device->id)->exists();
            $hadOthers = DB::table('known_devices')->where('user_id', $user->id)->exists();

            DB::table('known_devices')->upsert([[
                'user_id' => $user->id,
                'device_id' => $device->id,
                'name' => $device->name,
                'platform' => $device->platform,
                'first_seen_at' => now(),
                'last_seen_at' => now(),
            ]], ['user_id', 'device_id'], ['name', 'platform', 'last_seen_at']);

            $token = $user->createToken($device->name);
            $token->accessToken->forceFill([
                'device_id' => $device->id,
                'platform' => $device->platform,
                'app_version' => $device->appVersion,
            ])->save();

            return [$token->plainTextToken, ! $known && $hadOthers];
        });

        $this->audit->record('auth.signed_in', 'user', (string) $user->id, [
            'method' => $method,
            'device' => $device->name,
            'platform' => $device->platform,
            'new_account' => $newAccount,
            'new_device' => $newDevice,
        ], $user);

        if ($newDevice && $user->email !== null) {
            $user->notify(new NewDeviceSignIn($device, $method));
        }

        return ['token' => $token, 'new_device' => $newDevice];
    }

    /** @return Collection<int, PersonalAccessToken> */
    public function list(User $user): Collection
    {
        /** @var Collection<int, PersonalAccessToken> */
        return $user->tokens()->latest('last_used_at')->get();
    }

    public function revoke(User $user, PersonalAccessToken $token, string $reason): void
    {
        $token->delete();

        $this->audit->record($reason === 'sign_out' ? 'auth.signed_out' : 'auth.device_revoked', 'user', (string) $user->id, [
            'device' => $token->name,
            'platform' => $token->platform,
        ], $user);
    }
}
