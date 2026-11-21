<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CalendarConflict;
use App\Models\CalendarEvent;
use App\Services\Calendar\CalendarConflicts;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Daily: losing versions older than 30 days, and events deleted in REOS more
 * than 30 days ago (kept until then so Undo works), are removed for good.
 */
#[Signature('reos:calendar:prune')]
#[Description('Remove calendar conflict versions and deleted events after 30 days (B4.1)')]
final class CalendarPruneCommand extends Command
{
    public function handle(CalendarConflicts $conflicts): int
    {
        $versions = $conflicts->prune();
        $deleted = CalendarEvent::onlyTrashed()
            ->where('deleted_at', '<=', now()->subDays(CalendarConflict::KEEP_DAYS))
            ->whereNull('push_state')
            ->forceDelete();
        $events = is_int($deleted) ? $deleted : 0;

        $this->info("Removed {$versions} old versions and {$events} deleted events.");

        return self::SUCCESS;
    }
}
