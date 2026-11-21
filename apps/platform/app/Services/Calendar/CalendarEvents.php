<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Connections\Calendar\CalendarChoices;
use App\Connections\Calendar\CalendarMode;
use App\Connections\ConnectorRegistry;
use App\Jobs\PushCalendarEvent;
use App\Models\CalendarEvent;
use App\Models\Connection;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * A person's REOS calendar (B4.1, ADR 0017): what they see and change in
 * REOS. Every change is saved at once and written to the tool in the
 * background (`PushCalendarEvent`), so a slow tool never blocks the screen.
 *
 * - New events go to the calendar the person chose to write to; without
 *   one they stay in REOS.
 * - Events from a tool can be changed when that calendar allows it, never
 *   when it's shared as busy only.
 * - REOS deletes only events it made (Connections and Sync journey: "REOS
 *   never deletes in their tools"). Deleting can be undone.
 */
final readonly class CalendarEvents
{
    /** The fields people set. */
    public const FIELDS = ['title', 'kind', 'starts_at', 'ends_at', 'all_day', 'location', 'description', 'attendees', 'notify_guests', 'related_type', 'related_id'];

    /** @return Collection<int, CalendarEvent> Events overlapping the window, earliest first. */
    public function between(User $user, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return CalendarEvent::query()
            ->with('connection')
            ->where('user_id', $user->id)
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from)
            ->where('status', '!=', 'cancelled')
            ->orderBy('starts_at')
            ->orderBy('id')
            ->limit(500)
            ->get();
    }

    /**
     * Where events made in REOS go: one of the person's calendar connections.
     *
     * @return array{0: Connection, 1: string}|null
     */
    public function target(User $user): ?array
    {
        $connections = Connection::query()
            ->where('owner_type', Connection::OWNER_USER)->where('owner_id', $user->id)
            ->whereIn('integration_id', $this->calendarTools())
            ->orderBy('id')
            ->get();

        foreach ($connections as $connection) {
            $calendar = CalendarChoices::writeCalendar($connection);

            if ($calendar !== null) {
                return [$connection, $calendar];
            }
        }

        return null;
    }

    /** @param  array<string, mixed>  $values */
    public function create(User $user, array $values): CalendarEvent
    {
        $target = $this->target($user);
        $event = new CalendarEvent([
            'user_id' => $user->id,
            'source' => CalendarEvent::SOURCE_REOS,
            'kind' => 'appointment',
            'connection_id' => $target === null ? null : $target[0]->id,
            'calendar_id' => $target === null ? null : $target[1],
        ]);
        $event->forceFill([
            ...self::only($values),
            'local_updated_at' => Carbon::now(),
            'push_state' => $target === null ? null : CalendarEvent::PUSH_PENDING,
        ])->save();

        $this->send($event);

        return $event;
    }

    /**
     * @param  array<string, mixed>  $values
     *
     * @throws CalendarRefused
     */
    public function update(CalendarEvent $event, array $values): CalendarEvent
    {
        if (! $this->editable($event)) {
            throw new CalendarRefused($event->busy ? 'calendar.errors.busyOnly' : 'calendar.errors.readOnly');
        }

        // Kind and links are REOS's own; a tool's event stays an event.
        $values = $event->madeInReos() ? $values : array_diff_key($values, array_flip(['kind', 'related_type', 'related_id']));
        $event->forceFill([...self::only($values), 'local_updated_at' => Carbon::now()]);

        if (! $event->isDirty(array_diff(self::FIELDS, ['notify_guests']))) {
            return $event;
        }

        $event->push_state = $event->connection_id === null ? null : CalendarEvent::PUSH_PENDING;
        $event->push_error = null;
        $event->save();
        $this->send($event);

        return $event;
    }

    /** @throws CalendarRefused */
    public function delete(CalendarEvent $event): void
    {
        if (! $event->madeInReos()) {
            throw new CalendarRefused('calendar.errors.notOurs');
        }

        $event->forceFill([
            'local_updated_at' => Carbon::now(),
            'push_state' => $event->external_id === null ? null : CalendarEvent::PUSH_PENDING,
            'push_error' => null,
        ])->save();
        $event->delete();
        $this->send($event);
    }

    /** Undo a delete: back on the calendar, and back in the tool. */
    public function restore(CalendarEvent $event): CalendarEvent
    {
        $event->restore();
        // Written again as a new event if the tool already removed it.
        $event->forceFill([
            'local_updated_at' => Carbon::now(),
            'push_state' => $event->connection_id === null || $event->calendar_id === null ? null : CalendarEvent::PUSH_PENDING,
            'push_error' => null,
        ])->save();
        $this->send($event);

        return $event;
    }

    /** "Try again" after writing failed. */
    public function retry(CalendarEvent $event): void
    {
        if ($event->push_state === CalendarEvent::PUSH_FAILED) {
            $event->forceFill(['push_state' => CalendarEvent::PUSH_PENDING, 'push_error' => null])->save();
            $this->send($event);
        }
    }

    public function editable(CalendarEvent $event): bool
    {
        if ($event->trashed() || $event->busy || $event->status === 'cancelled') {
            return false;
        }

        if ($event->madeInReos() || $event->connection === null || $event->calendar_id === null) {
            return true;
        }

        return CalendarChoices::mode($event->connection, $event->calendar_id) === CalendarMode::Full
            && (CalendarChoices::find($event->connection, $event->calendar_id)->writable ?? false);
    }

    public function deletable(CalendarEvent $event): bool
    {
        return $event->madeInReos() && ! $event->trashed();
    }

    /**
     * A calendar to write to was chosen: REOS's own upcoming events that
     * aren't in any tool yet go there.
     */
    public function adopt(User $user): int
    {
        $target = $this->target($user);

        if ($target === null) {
            return 0;
        }

        $events = CalendarEvent::query()
            ->where('user_id', $user->id)->where('source', CalendarEvent::SOURCE_REOS)
            ->whereNull('connection_id')->where('ends_at', '>', Carbon::now())
            ->get();

        foreach ($events as $event) {
            $event->forceFill(['connection_id' => $target[0]->id, 'calendar_id' => $target[1], 'push_state' => CalendarEvent::PUSH_PENDING])->save();
            $this->send($event);
        }

        return $events->count();
    }

    /** Write everything still waiting for this connection (after reconnecting, or catching up). */
    public function resend(Connection $connection): void
    {
        $waiting = CalendarEvent::withTrashed()
            ->where('connection_id', $connection->id)->where('push_state', CalendarEvent::PUSH_PENDING)
            ->get(['id']);

        foreach ($waiting as $event) {
            PushCalendarEvent::dispatch($event->id);
        }
    }

    /** @return list<string> Tools in the engine whose driver is a calendar. */
    public function calendarTools(): array
    {
        return array_values(array_filter(ConnectorRegistry::ids(), ConnectorRegistry::isCalendar(...)));
    }

    private function send(CalendarEvent $event): void
    {
        if ($event->pending()) {
            PushCalendarEvent::dispatch($event->id)->afterCommit();
        }
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private static function only(array $values): array
    {
        $values = array_intersect_key($values, array_flip(self::FIELDS));

        if (array_key_exists('attendees', $values)) {
            $values['attendees'] = array_values(array_unique(array_map(
                static fn (string $email): string => mb_strtolower(trim($email)),
                array_filter((array) $values['attendees'], is_string(...)),
            )));
        }

        return $values;
    }
}
