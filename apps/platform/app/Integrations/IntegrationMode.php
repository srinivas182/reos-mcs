<?php

declare(strict_types=1);

namespace App\Integrations;

/**
 * How an integration runs, chosen by the super admin in Settings › Integrations.
 *
 * Simulator is the default, so every feature can be built and demonstrated
 * before a vendor approves REOS or sends keys.
 */
enum IntegrationMode: string
{
    case Off = 'off';
    case Simulator = 'simulator';
    case Sandbox = 'sandbox';
    case Live = 'live';

    /** The keys this mode runs on, if any. */
    public function environment(): ?CredentialEnvironment
    {
        return match ($this) {
            self::Sandbox => CredentialEnvironment::Sandbox,
            self::Live => CredentialEnvironment::Live,
            self::Off, self::Simulator => null,
        };
    }
}
