<?php

declare(strict_types=1);

namespace App\Sso;

use RuntimeException;

/**
 * A single sign-on that can't go ahead or settings that don't work.
 * `reason` is a translation key under `sso.errors.*`; the message is for logs.
 */
final class SsoProblem extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message = '', public readonly int $status = 422)
    {
        parent::__construct($message === '' ? $reason : $message);
    }
}
