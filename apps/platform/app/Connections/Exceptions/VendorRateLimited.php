<?php

declare(strict_types=1);

namespace App\Connections\Exceptions;

use RuntimeException;

/** The tool asked REOS to slow down. */
final class VendorRateLimited extends RuntimeException
{
    public function __construct(public readonly int $retryAfterSeconds = 60)
    {
        parent::__construct('Rate limited by the vendor.');
    }
}
