<?php

declare(strict_types=1);

namespace App\Integrations;

/** Whose vendor account the data comes from once REOS's keys are saved. */
enum AccountScope: string
{
    /** REOS's own account only. */
    case Platform = 'platform';
    /** Each person connects their own account from their settings. */
    case User = 'user';
    /** Each brokerage or school connects its own account. */
    case Tenant = 'tenant';
}
