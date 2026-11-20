<?php

declare(strict_types=1);

namespace App\Integrations;

/** Which of a vendor's systems a set of keys belongs to. */
enum CredentialEnvironment: string
{
    case Sandbox = 'sandbox';
    case Live = 'live';

    public function mode(): IntegrationMode
    {
        return match ($this) {
            self::Sandbox => IntegrationMode::Sandbox,
            self::Live => IntegrationMode::Live,
        };
    }
}
