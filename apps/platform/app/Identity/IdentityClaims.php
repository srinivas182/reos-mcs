<?php

declare(strict_types=1);

namespace App\Identity;

/** What a verified Google or Apple ID token says about the person. */
final readonly class IdentityClaims
{
    public function __construct(
        public IdentityProvider $provider,
        /** The provider's stable id for this person. */
        public string $subject,
        public ?string $email,
        public bool $emailVerified,
        public ?string $name = null,
    ) {}
}
