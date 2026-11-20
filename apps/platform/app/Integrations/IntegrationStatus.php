<?php

declare(strict_types=1);

namespace App\Integrations;

/** Where one integration stands: how it runs and which keys are saved. */
final readonly class IntegrationStatus
{
    public function __construct(
        public IntegrationMode $mode,
        public bool $hasSandboxKeys,
        public bool $hasLiveKeys,
    ) {}

    public function hasKeys(CredentialEnvironment $environment): bool
    {
        return $environment === CredentialEnvironment::Sandbox ? $this->hasSandboxKeys : $this->hasLiveKeys;
    }
}
