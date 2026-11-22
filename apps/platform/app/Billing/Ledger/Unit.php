<?php

declare(strict_types=1);

namespace App\Billing\Ledger;

/** What a ledger account counts (B5.3). */
enum Unit: string
{
    /** US dollars, in cents. */
    case Usd = 'usd';
    /** AI credits. */
    case Credit = 'credit';
    /** Seats a sponsor pays for. */
    case Seat = 'seat';
    /** Points earned for learning milestones. */
    case Point = 'point';
}
