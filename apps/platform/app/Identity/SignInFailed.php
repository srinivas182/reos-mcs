<?php

declare(strict_types=1);

namespace App\Identity;

use RuntimeException;

/**
 * A sign-in that can't go ahead. `reason` is the translation key shown to the
 * person (under `identity.errors.*`); the exception message is for logs only.
 */
final class SignInFailed extends RuntimeException
{
    /** @param  array<string, string|int>  $parameters */
    public function __construct(
        public readonly string $reason,
        string $message = '',
        public readonly array $parameters = [],
        public readonly int $status = 422,
    ) {
        parent::__construct($message === '' ? $reason : $message);
    }

    public static function unavailable(): self
    {
        return new self('identity.errors.unavailable', 'Provider switched off', status: 503);
    }

    public static function invalidToken(string $why): self
    {
        return new self('identity.errors.invalidToken', $why, status: 401);
    }
}
