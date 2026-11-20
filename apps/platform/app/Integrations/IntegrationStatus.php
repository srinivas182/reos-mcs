<?php

declare(strict_types=1);

namespace App\Integrations;

use Carbon\CarbonInterface;

/** Where one integration stands: how it runs and which keys are saved. */
final readonly class IntegrationStatus
{
    public function __construct(
        public IntegrationMode $mode,
        public bool $hasSandboxKeys,
        public bool $hasLiveKeys,
        /** Set when health checks paused it after repeated failures. */
        public ?CarbonInterface $pausedAt = null,
    ) {}

    /** What features should actually use: a paused integration acts as Off. */
    public function effectiveMode(): IntegrationMode
    {
        return $this->pausedAt !== null ? IntegrationMode::Off : $this->mode;
    }

    public function hasKeys(CredentialEnvironment $environment): bool
    {
        return $environment === CredentialEnvironment::Sandbox ? $this->hasSandboxKeys : $this->hasLiveKeys;
    }
}
