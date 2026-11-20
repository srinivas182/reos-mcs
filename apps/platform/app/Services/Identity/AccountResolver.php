<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Identity\IdentityClaims;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Finds or creates the one REOS account behind a sign-in, so a person who
 * uses Google today, Apple tomorrow and a password on the web is still one
 * account. Accounts are joined only on an email the provider has verified.
 */
final class AccountResolver
{
    /**
     * @return array{user: User, created: bool}
     */
    public function fromIdentity(IdentityClaims $claims, ?string $name = null): array
    {
        return DB::transaction(function () use ($claims, $name): array {
            $linked = SocialAccount::query()
                ->where('provider', $claims->provider)
                ->where('provider_user_id', $claims->subject)
                ->first();

            if ($linked !== null) {
                return ['user' => $linked->user, 'created' => false];
            }

            $user = $claims->emailVerified && $claims->email !== null
                ? User::query()->whereRaw('lower(email) = ?', [$claims->email])->lockForUpdate()->first()
                : null;
            $created = $user === null;

            if ($user === null) {
                $user = User::query()->create([
                    'name' => $name ?? $claims->name,
                    // An unverified email is never stored as the account's email.
                    'email' => $claims->emailVerified ? $claims->email : null,
                ]);
            } elseif ($user->email_verified_at === null) {
                // Someone signed up with this email but never proved it. The
                // provider has now proved who owns it, so the unproven
                // password and any sessions created with it are dropped
                // (prevents pre-registration account takeover).
                $user->forceFill(['password' => null])->save();
                $user->tokens()->delete();
            }

            if ($claims->emailVerified && $user->email_verified_at === null) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            if ($user->name === null && ($name ?? $claims->name) !== null) {
                $user->forceFill(['name' => $name ?? $claims->name])->save();
            }

            SocialAccount::query()->create([
                'user_id' => $user->id,
                'provider' => $claims->provider,
                'provider_user_id' => $claims->subject,
                'email' => $claims->email,
            ]);

            return ['user' => $user, 'created' => $created];
        });
    }

    /**
     * @return array{user: User, created: bool}
     */
    public function fromPhone(string $phone): array
    {
        return DB::transaction(function () use ($phone): array {
            $user = User::query()->where('phone', $phone)->lockForUpdate()->first();
            $created = $user === null;

            $user ??= new User;
            $user->forceFill(['phone' => $phone, 'phone_verified_at' => $user->phone_verified_at ?? now()])->save();

            return ['user' => $user, 'created' => $created];
        });
    }
}
