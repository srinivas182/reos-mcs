<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\PushCalendarEvent;
use App\Models\CalendarEvent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Every 15 minutes: write calendar changes still waiting (the tool was off,
 * paused or needed reconnecting when they were made).
 */
#[Signature('reos:calendar:catch-up {--minutes=5 : Changes waiting at least this long}')]
#[Description('Write REOS calendar changes still waiting for the tool (B4.1)')]
final class CalendarCatchUpCommand extends Command
{
    public function handle(): int
    {
        $queued = 0;

        CalendarEvent::withTrashed()
            ->where('push_state', CalendarEvent::PUSH_PENDING)
            ->where('updated_at', '<', now()->subMinutes(max(1, (int) $this->option('minutes'))))
            ->orderBy('id')
            ->chunkById(500, function ($events) use (&$queued): void {
                foreach ($events as $event) {
                    PushCalendarEvent::dispatch($event->id);
                    $queued++;
                }
            });

        $this->info("Queued {$queued} calendar changes.");

        return self::SUCCESS;
    }
}
