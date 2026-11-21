<?php

declare(strict_types=1);

namespace App\Authorization;

use RuntimeException;

/**
 * A role change or role use that can't go ahead. `reason` is the translation
 * key shown to the person (under `roles.errors.*`).
 */
final class RoleRefused extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        public readonly int $status = 422,
    ) {
        parent::__construct($reason);
    }

    public static function notHeld(): self
    {
        return new self('roles.errors.notHeld');
    }

    public static function twoStepRequired(): self
    {
        return new self('roles.errors.twoStepRequired', 403);
    }
}
