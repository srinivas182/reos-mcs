<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Connections\Exceptions\VendorRateLimited;
use App\Models\CalendarEvent;
use App\Services\Calendar\CalendarWriter;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Writes one REOS calendar change to the tool (B4.1). Queued on
 * `connections`, one at a time per event, retried with backoff; when the
 * retries run out the event shows "Not saved to <tool>" with Try again.
 */
final class PushCalendarEvent implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 60, 300, 900];

    public function __construct(public readonly int $eventId)
    {
        $this->onQueue('connections');
    }

    public function uniqueId(): string
    {
        return (string) $this->eventId;
    }

    public function handle(CalendarWriter $writer): void
    {
        $event = CalendarEvent::withTrashed()->with('connection')->find($this->eventId);

        if ($event === null) {
            return;
        }

        try {
            $writer->push($event);
        } catch (VendorRateLimited $e) {
            $this->release($e->retryAfterSeconds);
        }
    }

    public function failed(?Throwable $exception): void
    {
        CalendarEvent::withTrashed()->whereKey($this->eventId)->where('push_state', CalendarEvent::PUSH_PENDING)
            ->update(['push_state' => CalendarEvent::PUSH_FAILED, 'push_error' => 'calendar.errors.notSaved']);
    }
}
