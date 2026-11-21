<?php

declare(strict_types=1);

namespace App\Connections\Stores;

use App\Connections\StreamStore;
use App\Models\CalendarEvent;
use App\Models\Connection;
use Illuminate\Support\Carbon;

/** Calendar events from any calendar tool, keyed by the tool's own event id. */
final readonly class CalendarEventStore implements StreamStore
{
    private const COLUMNS = ['calendar_id', 'title', 'location', 'starts_at', 'ends_at', 'all_day', 'status', 'html_link', 'remote_updated_at', 'updated_at'];

    public function upsert(Connection $connection, array $records): int
    {
        if ($records === []) {
            return 0;
        }

        $now = Carbon::now();
        $rows = array_map(static fn (array $record): array => [
            'connection_id' => $connection->id,
            'external_id' => $record['external_id'],
            'calendar_id' => $record['calendar_id'] ?? 'primary',
            'title' => $record['title'] ?? null,
            'location' => $record['location'] ?? null,
            'starts_at' => $record['starts_at'],
            'ends_at' => $record['ends_at'],
            'all_day' => (bool) ($record['all_day'] ?? false),
            'status' => $record['status'] ?? 'confirmed',
            'html_link' => $record['html_link'] ?? null,
            'remote_updated_at' => $record['remote_updated_at'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $records);

        CalendarEvent::query()->upsert($rows, ['connection_id', 'external_id'], self::COLUMNS);

        return count($rows);
    }

    public function remove(Connection $connection, array $externalIds): int
    {
        if ($externalIds === []) {
            return 0;
        }

        $removed = CalendarEvent::query()->where('connection_id', $connection->id)->whereIn('external_id', $externalIds)->delete();

        return is_int($removed) ? $removed : 0;
    }

    public function forget(Connection $connection): void
    {
        CalendarEvent::query()->where('connection_id', $connection->id)->delete();
    }

    public function count(Connection $connection): int
    {
        return CalendarEvent::query()->where('connection_id', $connection->id)->count();
    }
}
