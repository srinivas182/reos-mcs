<?php

declare(strict_types=1);

namespace App\Tenancy;

/** A tenant's lifecycle (screen 59). */
enum TenantStatus: string
{
    /** In use. */
    case Active = 'active';

    /** In use on a trial until `trial_ends_at`. */
    case Trial = 'trial';

    /** Signed out and blocked (unpaid or on request); data kept. */
    case Suspended = 'suspended';

    /** Closed; data kept until the retention period ends. */
    case Archived = 'archived';
}
