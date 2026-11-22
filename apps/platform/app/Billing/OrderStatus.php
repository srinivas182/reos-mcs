<?php

declare(strict_types=1);

namespace App\Billing;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Expired = 'expired';
    case Refunded = 'refunded';
}
