<?php

declare(strict_types=1);

namespace App\Integrations;

/**
 * One value the super admin enters to connect a service (a client ID, an API key).
 * The label is `integrations.fields.<key>` in the translations.
 */
final readonly class ConnectionField
{
    public function __construct(
        public string $key,
        public bool $secret,
        public bool $required,
        public FieldKind $kind,
        public ?string $default,
    ) {}
}
