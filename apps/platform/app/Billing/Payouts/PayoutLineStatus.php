<?php

declare(strict_types=1);

namespace App\Billing\Payouts;

/** Where one payee's share of one sale stands (B5.2b). */
enum PayoutLineStatus: string
{
    /** Earned; goes out in the next weekly payout once the payee's Stripe account can take payouts. */
    case Pending = 'pending';
    /** Waiting for the session it pays for to happen (a points session, A17.1); then pending. */
    case Held = 'held';
    case Paid = 'paid';
    /** Refunded before it was paid out. */
    case Cancelled = 'cancelled';
    /** Refunded after it was paid out; the transfer was reversed. */
    case Reversed = 'reversed';
}
