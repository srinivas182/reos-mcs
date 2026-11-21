<?php

declare(strict_types=1);

namespace App\Connections\Calendar;

use App\Connections\Exceptions\AccessRevoked;
use App\Connections\Exceptions\RemoteChanged;
use App\Connections\Exceptions\VendorRateLimited;
use App\Connections\Exceptions\VendorUnavailable;
use App\Models\Connection;

/**
 * A calendar tool REOS writes to (B4.1 Google, B4.2 Microsoft 365), on top
 * of `ConnectorDriver` for reading. Events come back in the same shape the
 * `events` stream syncs:
 *
 * `external_id`, `calendar_id`, `title`, `location`, `description`,
 * `attendees` (emails), `starts_at`, `ends_at` (UTC), `all_day`, `busy`,
 * `status`, `html_link`, `etag`, `remote_updated_at`, and `reos_id` when
 * REOS made it.
 *
 * Every method may throw AccessRevoked, VendorRateLimited or VendorUnavailable.
 *
 * @phpstan-type RemoteEvent array<string, mixed>
 */
interface CalendarDriver
{
    /**
     * The account's calendars.
     *
     * @return list<CalendarInfo>
     *
     * @throws AccessRevoked|VendorRateLimited|VendorUnavailable
     */
    public function calendars(Connection $connection, string $accessToken): array;

    /**
     * Add an event. Tools that support it remember REOS's id on the event, so
     * a retry after a lost answer finds it instead of adding it twice.
     *
     * @return array<string, mixed>
     */
    public function insertEvent(Connection $connection, string $accessToken, string $calendarId, EventDraft $event): array;

    /**
     * Change an event, only if it is still the version REOS saw (`$etag`).
     *
     * @return array<string, mixed>
     *
     * @throws RemoteChanged when it changed at the tool since.
     */
    public function updateEvent(Connection $connection, string $accessToken, string $calendarId, string $eventId, EventDraft $event, ?string $etag): array;

    /**
     * The tool's current version, or null when it's gone.
     *
     * @return array<string, mixed>|null
     */
    public function getEvent(Connection $connection, string $accessToken, string $calendarId, string $eventId): ?array;

    /** Remove an event REOS made. Already gone counts as done. */
    public function deleteEvent(Connection $connection, string $accessToken, string $calendarId, string $eventId): void;
}
