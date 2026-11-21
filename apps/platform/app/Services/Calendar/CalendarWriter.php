<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Connections\Calendar\CalendarDriver;
use App\Connections\Calendar\EventDraft;
use App\Connections\ConnectorRegistry;
use App\Connections\Exceptions\AccessRevoked;
use App\Connections\Exceptions\RemoteChanged;
use App\Integrations\IntegrationMode;
use App\Jobs\PushCalendarEvent;
use App\Models\CalendarEvent;
use App\Models\Connection;
use App\Services\Connections\ConnectionHealth;
use App\Services\Connections\ConnectionTokens;

/**
 * Writes one REOS change to the calendar tool (B4.1, ADR 0017): add, change
 * (only over the version REOS saw) or delete.
 *
 * - Changed in the tool meanwhile: REOS reads the tool's version and the
 *   conflict rule decides (`CalendarSync`); if REOS's change is newer it is
 *   written again over the version just read.
 * - Deleted in the tool meanwhile: the deletion stands, REOS's version goes
 *   to the conflict trail.
 * - Refused sign-in: one refresh, then the connection needs reconnecting;
 *   the change stays waiting and is written after reconnecting.
 * - The tool off, paused or in another run mode: the change waits.
 * - Edited again while writing: the newer change is written next.
 */
final readonly class CalendarWriter
{
    private const ATTEMPTS = 3;

    public function __construct(
        private ConnectorRegistry $registry,
        private ConnectionTokens $tokens,
        private ConnectionHealth $health,
        private CalendarSync $sync,
    ) {}

    public function push(CalendarEvent $event): void
    {
        if (! $event->pending()) {
            return;
        }

        $connection = $event->connection;
        $calendar = $event->calendar_id;

        if ($connection === null || $calendar === null) {
            $event->forceFill(['push_state' => null, 'push_error' => null])->save();

            return;
        }

        $mode = $this->registry->mode($connection->integration_id);

        if ($mode === IntegrationMode::Off || $mode !== $connection->mode || ! $connection->status->syncs()) {
            return;
        }

        $driver = $this->registry->driver($connection->integration_id, $mode);

        if (! $driver instanceof CalendarDriver) {
            $event->forceFill(['push_state' => null])->save();

            return;
        }

        $refreshed = false;

        for ($attempt = 0; $attempt < self::ATTEMPTS; $attempt++) {
            $version = $event->version();

            try {
                $token = $this->tokens->accessToken($connection, $driver, forceRefresh: $refreshed);
                $this->finish($event, $version, $this->write($driver, $connection, $token, $event));

                return;
            } catch (AccessRevoked) {
                if ($refreshed) {
                    $this->health->reconnectNeeded($connection, 'connections.errors.refused');

                    return;
                }

                $refreshed = true;
            } catch (RemoteChanged) {
                $token = $this->tokens->accessToken($connection, $driver);
                $remote = $driver->getEvent($connection, $token, $calendar, (string) $event->external_id);

                if ($remote === null) {
                    $this->sync->gone($connection, $calendar, (string) $event->external_id);

                    return;
                }

                $this->sync->incoming($connection, $remote);
                $event->refresh();

                if (! $event->pending()) {
                    // The tool's change was newer and is now in REOS.
                    return;
                }
            }
        }
    }

    /** @return array<string, mixed> What the tool now holds (empty after a delete). */
    private function write(CalendarDriver $driver, Connection $connection, string $token, CalendarEvent $event): array
    {
        $calendar = (string) $event->calendar_id;

        if ($event->trashed()) {
            if ($event->external_id !== null) {
                $driver->deleteEvent($connection, $token, $calendar, $event->external_id);
            }

            return [];
        }

        if ($event->external_id === null) {
            return $driver->insertEvent($connection, $token, $calendar, EventDraft::of($event));
        }

        return $driver->updateEvent($connection, $token, $calendar, $event->external_id, EventDraft::of($event), $event->etag);
    }

    /**
     * @param  array<string, mixed>  $version  The REOS version that was written.
     * @param  array<string, mixed>  $remote
     */
    private function finish(CalendarEvent $event, array $version, array $remote): void
    {
        $fields = $remote === []
            ? ['external_id' => null, 'etag' => null, 'html_link' => null, 'remote_updated_at' => null]
            : [
                'external_id' => $remote['external_id'] ?? $event->external_id,
                'etag' => $remote['etag'] ?? null,
                'html_link' => $remote['html_link'] ?? null,
                'remote_updated_at' => $remote['remote_updated_at'] ?? null,
            ];

        $fresh = CalendarEvent::withTrashed()->find($event->id);

        if ($fresh === null) {
            return;
        }

        // Edited again while writing: keep it waiting and write the newer version.
        $changed = $fresh->version() !== $version;
        $fresh->forceFill([...$fields, 'push_state' => $changed ? CalendarEvent::PUSH_PENDING : null, 'push_error' => null])->save();

        if ($changed) {
            PushCalendarEvent::dispatch($fresh->id);
        }
    }
}
