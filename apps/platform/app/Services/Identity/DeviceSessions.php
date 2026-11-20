<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Identity\DeviceInfo;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One token per installed app. Signing in again on the same phone replaces
 * its token; a phone the person has never used before triggers an email.
 */
final readonly class DeviceSessions
{
    public function __construct(private AuditLogger $audit, private SignInRecorder $recorder) {}

    /**
     * @return array{token: string, new_device: bool}
     */
    public function issue(User $user, DeviceInfo $device, string $method, bool $newAccount): array
    {
        $token = DB::transaction(function () use ($user, $device): string {
            $user->tokens()->where('device_id', $device->id)->delete();

            $token = $user->createToken($device->name);
            $token->accessToken->forceFill([
                'device_id' => $device->id,
                'platform' => $device->platform,
                'app_version' => $device->appVersion,
            ])->save();

            return $token->plainTextToken;
        });

        $newDevice = $this->recorder->record($user, $device, $method, $newAccount);

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
