<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Models\CalendarConflict;
use App\Models\CalendarEvent;
use App\Models\Connection;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * What arrives from a calendar tool, applied to the person's REOS calendar
 * (B4.1, ADR 0017).
 *
 * - New events are added; changes are applied; events deleted in the tool
 *   are removed (REOS never deletes in the tool on its own).
 * - The tool's echo of REOS's own write (same version) changes nothing.
 * - Changed on both sides before either saw the other: **newest change
 *   wins**. The losing version goes to the conflict trail for 30 days with
 *   Undo (`CalendarConflicts`). A deletion in the tool always wins, and the
 *   REOS version is kept so Undo can bring it back.
 */
final readonly class CalendarSync
{
    public function __construct(private CalendarConflicts $conflicts) {}

    /**
     * @param  array<string, mixed>  $record  As `CalendarDriver` describes it.
     * @return bool Whether REOS saved anything.
     */
    public function incoming(Connection $connection, array $record): bool
    {
        $userId = $this->owner($connection);
        $calendar = $record['calendar_id'] ?? null;
        $external = $record['external_id'] ?? null;

        if ($userId === null || ! is_string($calendar) || ! is_string($external)) {
            return false;
        }

        $event = CalendarEvent::withTrashed()
            ->where('connection_id', $connection->id)->where('calendar_id', $calendar)->where('external_id', $external)
            ->first();

        // An event REOS added whose answer was lost: the tool remembers REOS's id.
        if ($event === null && is_int($record['reos_id'] ?? null)) {
            $event = CalendarEvent::withTrashed()->where('id', $record['reos_id'])->where('user_id', $userId)->whereNull('external_id')->first();
        }

        if ($event === null) {
            $event = new CalendarEvent(['user_id' => $userId, 'source' => $connection->integration_id, 'kind' => 'event']);
            $this->apply($event, $connection, $record);

            return true;
        }

        if ($event->etag !== null && $event->etag === ($record['etag'] ?? null)) {
            return false;
        }

        if (! $event->unsent()) {
            $this->apply($event, $connection, $record);

            return true;
        }

        // Changed in REOS (not yet written back) and in the tool.
        $localAt = $event->local_updated_at ?? $event->updated_at ?? Carbon::now();
        $remoteAt = $record['remote_updated_at'] ?? null;
        $remoteAt = $remoteAt instanceof DateTimeInterface ? CarbonImmutable::parse($remoteAt) : CarbonImmutable::now();

        if ($remoteAt->greaterThan($localAt)) {
            $this->conflicts->record($event, CalendarConflict::KEPT_TOOL, $event->trashed() ? CalendarConflict::DELETED_IN_REOS : CalendarConflict::EDITED_BOTH, $event->version());

            if ($event->trashed()) {
                $event->restore();
            }

            $event->push_state = null;
            $event->push_error = null;
            $this->apply($event, $connection, $record);

            return true;
        }

        $this->conflicts->record($event, CalendarConflict::KEPT_REOS, CalendarConflict::EDITED_BOTH, self::version($record));
        // REOS's version goes back over the tool's: write against the version just seen.
        $event->forceFill([
            'etag' => $record['etag'] ?? null,
            'remote_updated_at' => $record['remote_updated_at'] ?? null,
        ])->save();

        return true;
    }

    /** The tool deleted or cancelled an event. */
    public function gone(Connection $connection, string $calendarId, string $externalId): bool
    {
        $event = CalendarEvent::withTrashed()
            ->where('connection_id', $connection->id)->where('calendar_id', $calendarId)->where('external_id', $externalId)
            ->first();

        if ($event === null) {
            return false;
        }

        // REOS had changed it: keep that version so Undo can make it again.
        if ($event->unsent() && ! $event->trashed()) {
            $this->conflicts->record($event, CalendarConflict::KEPT_TOOL, CalendarConflict::DELETED_IN_TOOL, [
                ...$event->version(),
                'kind' => $event->kind,
                'source' => $event->source,
            ], keepEvent: false);
        }

        $event->forceDelete();

        return true;
    }

    /**
     * The connection is going: the tool's events go with it; REOS's own stay
     * on the REOS calendar, no longer linked to the tool.
     */
    public function forget(Connection $connection): void
    {
        CalendarEvent::withTrashed()->where('connection_id', $connection->id)->where('source', '!=', CalendarEvent::SOURCE_REOS)->forceDelete();
        CalendarEvent::withTrashed()->where('connection_id', $connection->id)->update([
            'connection_id' => null, 'calendar_id' => null, 'external_id' => null, 'etag' => null, 'html_link' => null,
            'remote_updated_at' => null, 'push_state' => null, 'push_error' => null,
        ]);
    }

    /**
     * A calendar read differently now (or not at all): its tool events go,
     * and the next sync brings them back as chosen.
     */
    public function dropCalendar(Connection $connection, string $calendarId): void
    {
        CalendarEvent::withTrashed()
            ->where('connection_id', $connection->id)->where('calendar_id', $calendarId)
            ->where('source', '!=', CalendarEvent::SOURCE_REOS)
            ->forceDelete();
    }

    /** @param  array<string, mixed>  $record */
    private function apply(CalendarEvent $event, Connection $connection, array $record): void
    {
        $event->forceFill([
            'connection_id' => $connection->id,
            'calendar_id' => $record['calendar_id'],
            'external_id' => $record['external_id'],
            'title' => $record['title'] ?? null,
            'location' => $record['location'] ?? null,
            'description' => $record['description'] ?? null,
            'attendees' => array_values(array_filter((array) ($record['attendees'] ?? []), is_string(...))),
            'starts_at' => $record['starts_at'],
            'ends_at' => $record['ends_at'],
            'all_day' => (bool) ($record['all_day'] ?? false),
            'busy' => (bool) ($record['busy'] ?? false),
            'status' => is_string($record['status'] ?? null) ? $record['status'] : 'confirmed',
            'html_link' => $record['html_link'] ?? null,
            'etag' => $record['etag'] ?? null,
            'remote_updated_at' => $record['remote_updated_at'] ?? null,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private static function version(array $record): array
    {
        $at = static fn (mixed $value): ?string => $value instanceof DateTimeInterface ? CarbonImmutable::parse($value)->utc()->toIso8601String() : null;

        return [
            'title' => $record['title'] ?? null,
            'starts_at' => $at($record['starts_at'] ?? null),
            'ends_at' => $at($record['ends_at'] ?? null),
            'all_day' => (bool) ($record['all_day'] ?? false),
            'location' => $record['location'] ?? null,
            'description' => $record['description'] ?? null,
            'attendees' => array_values(array_filter((array) ($record['attendees'] ?? []), is_string(...))),
            'deleted' => false,
        ];
    }

    private function owner(Connection $connection): ?int
    {
        return $connection->owner_type === Connection::OWNER_USER ? $connection->owner_id : null;
    }
}
