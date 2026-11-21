<?php

declare(strict_types=1);

namespace App\Connections;

use Carbon\CarbonImmutable;

/** What a tool hands back after sign-in or a refresh. */
final readonly class TokenSet
{
    /** @param  list<string>  $scopes */
    public function __construct(
        public string $accessToken,
        public ?string $refreshToken,
        public ?CarbonImmutable $expiresAt,
        public array $scopes,
    ) {}
}
