<?php

declare(strict_types=1);

namespace App\Billing;

/** Where access came from: a one-time order, a subscription, a sponsor's seat or code, or an admin. */
enum EntitlementSource: string
{
    case Order = 'order';
    case Subscription = 'subscription';
    case Sponsor = 'sponsor';
    case Admin = 'admin';
}
