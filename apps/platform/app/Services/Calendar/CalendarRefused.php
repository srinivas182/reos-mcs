<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use DomainException;

/** A calendar change REOS won't make, with the translation key that says why. */
final class CalendarRefused extends DomainException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
