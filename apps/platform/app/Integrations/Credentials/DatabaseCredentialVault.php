<?php

declare(strict_types=1);

namespace App\Integrations\Credentials;

use App\Integrations\CredentialEnvironment;
use App\Models\IntegrationCredential;
use App\Models\User;
use JsonException;
use RuntimeException;

/**
 * Keeps integration keys in the `integration_credentials` table, encrypted as
 * one JSON payload per integration and environment.
 */
final readonly class DatabaseCredentialVault implements CredentialVault
{
    public function __construct(private CredentialCipher $cipher) {}

    public function get(string $integrationId, CredentialEnvironment $environment): array
    {
        $record = IntegrationCredential::query()
            ->where('integration_id', $integrationId)
            ->where('environment', $environment)
            ->first();

        if ($record === null) {
            return [];
        }

        try {
            $values = json_decode($this->cipher->decrypt($record->payload), true, 4, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("Saved keys for {$integrationId} are unreadable.", 0, $exception);
        }

        return is_array($values)
            ? array_filter($values, static fn (mixed $value, mixed $key): bool => is_string($key) && is_string($value), ARRAY_FILTER_USE_BOTH)
            : [];
    }

    public function put(string $integrationId, CredentialEnvironment $environment, array $values, ?User $actor): void
    {
        IntegrationCredential::query()->updateOrCreate(
            ['integration_id' => $integrationId, 'environment' => $environment],
            [
                'payload' => $this->cipher->encrypt(json_encode($values, JSON_THROW_ON_ERROR)),
                'updated_by' => $actor?->id,
            ],
        );
    }

    public function forget(string $integrationId, CredentialEnvironment $environment): void
    {
        IntegrationCredential::query()
            ->where('integration_id', $integrationId)
            ->where('environment', $environment)
            ->delete();
    }

    public function saved(): array
    {
        $saved = [];

        foreach (IntegrationCredential::query()->get(['integration_id', 'environment']) as $record) {
            $saved[$record->integration_id][] = $record->environment;
        }

        return $saved;
    }

    /**
     * Re-encrypts every saved payload with the current key (after rotation).
     *
     * @return int how many payloads were re-encrypted
     */
    public function reencrypt(): int
    {
        $count = 0;

        IntegrationCredential::query()->lazyById()->each(function (IntegrationCredential $record) use (&$count): void {
            $record->payload = $this->cipher->encrypt($this->cipher->decrypt($record->payload));
            $record->save();
            $count++;
        });

        return $count;
    }
}
