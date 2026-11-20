<?php

declare(strict_types=1);

namespace App\Integrations\Checks;

/** Who started a check. */
enum CheckTrigger: string
{
    /** The super admin pressed "Test connection". */
    case Manual = 'manual';
    /** The 15-minute health check. */
    case Scheduled = 'scheduled';
}
