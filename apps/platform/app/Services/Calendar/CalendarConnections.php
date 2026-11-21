<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Connections\Calendar\CalendarChoices;
use App\Connections\Calendar\CalendarDriver;
use App\Connections\Calendar\CalendarMode;
use App\Connections\ConnectorRegistry;
use App\Models\Connection;
use App\Models\User;
use App\Services\Connections\Connections;
use App\Services\Connections\ConnectionTokens;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * A calendar connection's choices (Connections and Sync journey, screen 16;
 * web Connections › Manage): which calendars REOS reads and how, and which
 * one REOS's own events go to.
 */
final readonly class CalendarConnections
{
    /** How often the list of calendars is refreshed after a sync. */
    private const LIST_EVERY_MINUTES = 60;

    public function __construct(
        private ConnectorRegistry $registry,
        private ConnectionTokens $tokens,
        private CalendarSync $sync,
        private CalendarEvents $events,
        private Connections $connections,
    ) {}

    /**
     * Save what the person chose. `$writeCalendar` is a calendar id, '' for
     * "keep REOS events in REOS", or null to leave it as it is.
     *
     * @param  array<string, string>  $modes  calendar id → full, busy or off
     *
     * @throws ValidationException
     */
    public function choose(Connection $connection, array $modes, ?string $writeCalendar): Connection
    {
        $known = [];
        foreach (CalendarChoices::known($connection) as $calendar) {
            $known[$calendar->id] = $calendar;
        }

        $saved = [];
        $changed = [];

        foreach ($known as $id => $calendar) {
            $mode = CalendarMode::tryFrom($modes[$id] ?? '') ?? CalendarChoices::mode($connection, $id);
            $saved[$id] = $mode->value;

            if ($mode !== CalendarChoices::mode($connection, $id)) {
                $changed[] = $id;
            }
        }

        if (array_diff_key($modes, $known) !== []) {
            throw ValidationException::withMessages(['calendars' => __('calendar.errors.unknownCalendar')]);
        }

        $write = $writeCalendar ?? CalendarChoices::writeCalendar($connection);

        if ($write !== null && $write !== '') {
            if (! isset($known[$write]) || ! $known[$write]->writable) {
                throw ValidationException::withMessages(['write_calendar' => __('calendar.errors.notWritable')]);
            }
            if (($saved[$write] ?? null) !== CalendarMode::Full->value) {
                throw ValidationException::withMessages(['write_calendar' => __('calendar.errors.writeNeedsFull')]);
            }
        }

        $settings = [...$connection->settings, 'calendars' => $saved, 'write_calendar' => $write === '' ? null : $write];
        $connection->forceFill(['settings' => $settings]);

        if ($changed !== []) {
            foreach ($changed as $id) {
                $this->sync->dropCalendar($connection, $id);
            }

            // Read every calendar again as now chosen.
            $connection->cursors = array_diff_key($connection->cursors, ['events' => true]);
        }

        $connection->save();

        if (is_string($settings['write_calendar'])) {
            $this->onlyWriter($connection);
            $user = $this->user($connection);
            if ($user !== null) {
                $this->events->adopt($user);
            }
        }

        if ($changed !== []) {
            $this->connections->syncNow($connection);
        }

        return $connection;
    }

    /**
     * Just connected (or reconnected): the first calendar connection becomes
     * where REOS's events go, and anything waiting is written.
     */
    public function connected(Connection $connection): void
    {
        if (! ConnectorRegistry::isCalendar($connection->integration_id)) {
            return;
        }

        $user = $this->user($connection);

        if ($user !== null && $this->events->target($user) === null && ! array_key_exists('write_calendar', $connection->settings)) {
            $connection->forceFill(['settings' => [...$connection->settings, 'write_calendar' => CalendarChoices::PRIMARY]])->save();
            $this->events->adopt($user);
        }

        $this->events->resend($connection);
    }

    /** Refresh the list of calendars, at most hourly unless asked. */
    public function refreshCalendars(Connection $connection, CalendarDriver $driver, bool $force = false): void
    {
        $listedAt = $connection->meta['calendars_listed_at'] ?? null;

        if (! $force && is_string($listedAt) && Carbon::parse($listedAt)->gt(Carbon::now()->subMinutes(self::LIST_EVERY_MINUTES))) {
            return;
        }

        try {
            $calendars = $driver->calendars($connection, $this->tokens->accessToken($connection, $this->registry->driver($connection->integration_id, $connection->mode)));
        } catch (Throwable) {
            // The list is a convenience; the last one stays.
            return;
        }

        $connection->forceFill(['meta' => [
            ...$connection->meta,
            'calendars' => array_map(static fn ($calendar): array => $calendar->toArray(), $calendars),
            'calendars_listed_at' => Carbon::now()->toIso8601String(),
        ]])->save();
    }

    /** One calendar per person receives REOS's events. */
    private function onlyWriter(Connection $chosen): void
    {
        Connection::query()
            ->where('owner_type', $chosen->owner_type)->where('owner_id', $chosen->owner_id)
            ->whereIn('integration_id', $this->events->calendarTools())
            ->whereKeyNot($chosen->id)
            ->get()
            ->each(static function (Connection $other): void {
                if (($other->settings['write_calendar'] ?? null) !== null) {
                    $other->forceFill(['settings' => [...$other->settings, 'write_calendar' => null]])->save();
                }
            });
    }

    private function user(Connection $connection): ?User
    {
        return $connection->owner_type === Connection::OWNER_USER ? User::query()->find($connection->owner_id) : null;
    }
}
