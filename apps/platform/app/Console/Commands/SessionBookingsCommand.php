<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Sessions\SessionBookings;
use Illuminate\Console\Command;

/**
 * Booked sessions (A10.2a), every minute: holds whose payment never came
 * are released; an instructor who hasn't joined 10 minutes in means a full
 * refund; ended sessions are marked complete for rating and tasks.
 */
final class SessionBookingsCommand extends Command
{
    protected $signature = 'reos:sessions:bookings';

    protected $description = 'Release unpaid holds, refund no-shows and complete ended sessions';

    public function handle(SessionBookings $bookings): int
    {
        $released = $bookings->releaseHolds();
        $refunded = $bookings->refundNoShows();
        $completed = $bookings->complete();
        $this->info("Released {$released}, refunded {$refunded}, completed {$completed}.");

        return self::SUCCESS;
    }
}
