<?php

declare(strict_types=1);

namespace App\Identity;

/** One-tap sign-in providers, and their entries in the integrations register. */
enum IdentityProvider: string
{
    case Google = 'google';
    case Apple = 'apple';

    public function integrationId(): string
    {
        return match ($this) {
            self::Google => 'google-sign-in',
            self::Apple => 'apple-sign-in',
        };
    }

    /**
     * Who signs the provider's ID tokens.
     *
     * @return list<string>
     */
    public function issuers(): array
    {
        return match ($this) {
            self::Google => ['https://accounts.google.com', 'accounts.google.com'],
            self::Apple => ['https://appleid.apple.com'],
        };
    }

    /** Where the provider publishes its signing keys. */
    public function keysUrl(): string
    {
        return match ($this) {
            self::Google => 'https://www.googleapis.com/oauth2/v3/certs',
            self::Apple => 'https://appleid.apple.com/auth/keys',
        };
    }
}
