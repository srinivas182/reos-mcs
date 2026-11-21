<?php

declare(strict_types=1);

namespace App\Connections\Calendar;

use App\Models\CalendarEvent;
use Carbon\CarbonImmutable;

/** An event as REOS writes it to a calendar tool. */
final readonly class EventDraft
{
    /**
     * @param  list<string>  $attendees  Guest emails.
     * @param  string  $key  This event at this version: a retry of the same write has the same key.
     */
    public function __construct(
        public string $title,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public bool $allDay,
        public ?string $location,
        public ?string $description,
        public array $attendees,
        public bool $notifyGuests,
        public int $reosId,
        public string $key,
    ) {}

    public static function of(CalendarEvent $event): self
    {
        return new self(
            (string) $event->title,
            CarbonImmutable::parse($event->starts_at)->utc(),
            CarbonImmutable::parse($event->ends_at)->utc(),
            $event->all_day,
            $event->location,
            $event->description,
            $event->attendees ?? [],
            $event->notify_guests,
            $event->id,
            $event->id.':'.($event->local_updated_at?->getTimestampMs() ?? 0),
        );
    }
}
