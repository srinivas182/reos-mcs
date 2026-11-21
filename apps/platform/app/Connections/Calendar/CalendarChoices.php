<?php

declare(strict_types=1);

namespace App\Connections\Calendar;

use App\Models\Connection;

/**
 * A calendar connection's choices (screen 16), kept in its `settings`:
 *
 * - `calendars`: calendar id → `full`, `busy` or `off`. Unlisted calendars
 *   are off, except the main one, which is read in full: private by default.
 * - `write_calendar`: where events made in REOS go, or none. One connection
 *   per person holds it (`CalendarConnections::chooseCalendars`).
 *
 * The list of calendars itself is kept in `meta.calendars`, refreshed after
 * syncs.
 */
final class CalendarChoices
{
    public const PRIMARY = 'primary';

    public static function mode(Connection $connection, string $calendarId): CalendarMode
    {
        $saved = $connection->settings['calendars'][$calendarId] ?? null;

        if (is_string($saved) && ($mode = CalendarMode::tryFrom($saved)) !== null) {
            return $mode;
        }

        return $calendarId === self::PRIMARY ? CalendarMode::Full : CalendarMode::Off;
    }

    /**
     * Calendars REOS reads, main calendar first.
     *
     * @return list<string>
     */
    public static function synced(Connection $connection): array
    {
        $ids = [self::PRIMARY, ...array_keys($connection->settings['calendars'] ?? [])];
        $ids = array_values(array_unique(array_map(strval(...), $ids)));

        return array_values(array_filter($ids, static fn (string $id): bool => self::mode($connection, $id) !== CalendarMode::Off));
    }

    public static function writeCalendar(Connection $connection): ?string
    {
        $id = $connection->settings['write_calendar'] ?? null;

        return is_string($id) && $id !== '' && self::mode($connection, $id) === CalendarMode::Full ? $id : null;
    }

    /**
     * The account's calendars as last listed, or just the main one before the first list.
     *
     * @return list<CalendarInfo>
     */
    public static function known(Connection $connection): array
    {
        $rows = $connection->meta['calendars'] ?? null;
        $calendars = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            $calendar = is_array($row) ? CalendarInfo::fromArray($row) : null;

            if ($calendar !== null) {
                $calendars[] = $calendar;
            }
        }

        return $calendars !== [] ? $calendars : [new CalendarInfo(self::PRIMARY, $connection->account_label ?? self::PRIMARY, true, true)];
    }

    public static function find(Connection $connection, string $calendarId): ?CalendarInfo
    {
        foreach (self::known($connection) as $calendar) {
            if ($calendar->id === $calendarId) {
                return $calendar;
            }
        }

        return null;
    }
}
