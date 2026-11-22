<?php

declare(strict_types=1);

namespace App\Billing;

/** Past due: the last renewal failed; access continues through the 7-day grace period. */
enum SubscriptionStatus: string
{
    case Active = 'active';
    case PastDue = 'past_due';
    case Canceled = 'canceled';
}
