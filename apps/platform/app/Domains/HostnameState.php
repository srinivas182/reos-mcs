<?php

declare(strict_types=1);

namespace App\Domains;

/** What the custom-domain provider says about a tenant's domain (A4.2b). */
final readonly class HostnameState
{
    public const PENDING = 'pending';

    public const ACTIVE = 'active';

    public const FAILED = 'failed';

    /**
     * @param  list<array{type: string, name: string, value: string}>  $records  DNS records the tenant adds
     */
    public function __construct(
        public string $providerId,
        /** `pending`, `active` or `failed`. */
        public string $status,
        /** `pending` or `active`: the SSL certificate. */
        public string $ssl,
        public array $records,
        public ?string $error = null,
    ) {}

    public function live(): bool
    {
        return $this->status === self::ACTIVE && $this->ssl === self::ACTIVE;
    }
}
