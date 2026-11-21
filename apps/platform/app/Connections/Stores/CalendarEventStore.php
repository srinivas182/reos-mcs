<?php

declare(strict_types=1);

namespace App\Connections\Stores;

use App\Connections\StreamStore;
use App\Models\CalendarEvent;
use App\Models\Connection;
use App\Services\Calendar\CalendarSync;

/**
 * Calendar events from any calendar tool, into the person's REOS calendar.
 * Each record goes through `CalendarSync`, which knows what REOS changed
 * that the tool hasn't seen yet (the conflict rule).
 *
 * Removed events are named by `key()`: the calendar and the tool's event id.
 */
final readonly class CalendarEventStore implements StreamStore
{
    public function __construct(private CalendarSync $sync) {}

    /** One event in one calendar. Calendar and event ids never contain a slash. */
    public static function key(string $calendarId, string $eventId): string
    {
        return "{$calendarId}/{$eventId}";
    }

    /** @return array{0: string, 1: string}|null */
    public static function split(string $key): ?array
    {
        $at = strrpos($key, '/');

        return $at === false || $at === 0 || $at === strlen($key) - 1 ? null : [substr($key, 0, $at), substr($key, $at + 1)];
    }

    public function upsert(Connection $connection, array $records): int
    {
        $written = 0;

        foreach ($records as $record) {
            $written += $this->sync->incoming($connection, $record) ? 1 : 0;
        }

        return $written;
    }

    public function remove(Connection $connection, array $externalIds): int
    {
        $removed = 0;

        foreach ($externalIds as $key) {
            $parts = self::split($key);
            $removed += $parts !== null && $this->sync->gone($connection, $parts[0], $parts[1]) ? 1 : 0;
        }

        return $removed;
    }

    public function forget(Connection $connection): void
    {
        $this->sync->forget($connection);
    }

    public function count(Connection $connection): int
    {
        return CalendarEvent::query()->where('connection_id', $connection->id)->count();
    }
}
