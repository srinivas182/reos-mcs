<?php

declare(strict_types=1);

namespace App\Casts;

use App\Integrations\Credentials\CredentialCipher;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Encrypts a column with the Integration Hub's key (REOS_CREDENTIALS_KEY,
 * AES-256-GCM), not APP_KEY, so vendor tokens follow the same rotation as
 * the hub's keys (`reos:credentials:rekey`).
 *
 * @implements CastsAttributes<string|null, string|null>
 */
final class HubEncrypted implements CastsAttributes
{
    /** @param  array<string, mixed>  $attributes */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return is_string($value) && $value !== '' ? app(CredentialCipher::class)->decrypt($value) : null;
    }

    /** @param  array<string, mixed>  $attributes */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return is_string($value) && $value !== '' ? app(CredentialCipher::class)->encrypt($value) : null;
    }
}
