<?php

declare(strict_types=1);

namespace App\Enums;

/** A real estate licence (A5.1). Designated brokers and delegated supervisors are a `BrokerRole`. */
enum LicenceType: string
{
    case SalesAgent = 'sales_agent';
    case Broker = 'broker';
}
