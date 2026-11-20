<?php

declare(strict_types=1);

namespace App\Integrations\Credentials;

use App\Integrations\CredentialEnvironment;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Where integration keys are kept. Today the database (encrypted with
 * {@see CredentialCipher}); once the AWS account exists, an AWS Secrets
 * Manager implementation replaces it without changing any screen (ADR 0009).
 */
interface CredentialVault
{
    /**
     * The saved values, or an empty array when nothing is saved.
     *
     * @return array<string, string>
     */
    public function get(string $integrationId, CredentialEnvironment $environment): array;

    /**
     * Replaces the saved values.
     *
     * @param  array<string, string>  $values
     */
    public function put(string $integrationId, CredentialEnvironment $environment, array $values, ?User $actor): void;

    /** When these keys were last saved, or null when there are none. */
    public function updatedAt(string $integrationId, CredentialEnvironment $environment): ?CarbonInterface;

    public function forget(string $integrationId, CredentialEnvironment $environment): void;

    /**
     * Which environments have keys, per integration (one query for the list page).
     *
     * @return array<string, list<CredentialEnvironment>>
     */
    public function saved(): array;
}
