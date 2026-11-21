<?php

declare(strict_types=1);

namespace App\Sso;

/** Who a tenant's identity provider says signed in, after every check passed. */
final readonly class SsoClaims
{
    public function __construct(
        /** The provider's stable id for the person (SAML NameID or OIDC `sub`). */
        public string $subject,
        public ?string $email,
        public ?string $name = null,
    ) {}
}
