<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Identity\DeviceInfo;
use App\Models\User;
use App\Notifications\NewDeviceSignIn;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * What happens on every successful sign-in, on the phone or the web:
 * remember the device, email the person if it's new to them, cancel a
 * pending account deletion, and write the audit row.
 */
final readonly class SignInRecorder
{
    public function __construct(
        private AuditLogger $audit,
        private AccountDeletion $deletion,
    ) {}

    /**
     * @return bool whether this was a device the person had never used
     */
    public function record(User $user, DeviceInfo $device, string $method, bool $newAccount): bool
    {
        $newDevice = DB::transaction(function () use ($user, $device): bool {
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

            return ! $known && $hadOthers;
        });

        $this->audit->record('auth.signed_in', 'user', (string) $user->id, [
            'method' => $method,
            'device' => $device->name,
            'platform' => $device->platform,
            'new_account' => $newAccount,
            'new_device' => $newDevice,
        ], $user);

        // Signing in during the 30-day grace period keeps the account.
        if ($user->deletion_requested_at !== null) {
            $this->deletion->cancel($user);
        }

        if ($newDevice && $user->email !== null) {
            $user->notify(new NewDeviceSignIn($device, $method));
        }

        return $newDevice;
    }
}
