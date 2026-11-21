<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Models\CalendarConflict;
use App\Models\CalendarEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * The calendar conflict trail (B4.1; Sprint 4 "our own addition" 5): when an
 * event changed in REOS and in the tool, the newest change wins and the
 * losing version is kept here for 30 days, shown on the event with Undo.
 * Undo makes the losing version the newest change.
 */
final readonly class CalendarConflicts
{
    /** @param  array<string, mixed>  $losing */
    public function record(CalendarEvent $event, string $kept, string $reason, array $losing, bool $keepEvent = true): CalendarConflict
    {
        return CalendarConflict::query()->create([
            'user_id' => $event->user_id,
            'calendar_event_id' => $keepEvent ? $event->id : null,
            'kept' => $kept,
            'reason' => $reason,
            'losing' => $losing,
        ]);
    }

    /** The newest conflict still shown on an event. */
    public function openFor(CalendarEvent $event): ?CalendarConflict
    {
        return CalendarConflict::query()
            ->where('calendar_event_id', $event->id)->whereNull('undone_at')
            ->where('created_at', '>', Carbon::now()->subDays(CalendarConflict::KEEP_DAYS))
            ->latest('id')
            ->first();
    }

    /**
     * Conflicts still shown for these events, newest per event.
     *
     * @param  list<int>  $eventIds
     * @return array<int, CalendarConflict>
     */
    public function openForMany(array $eventIds): array
    {
        if ($eventIds === []) {
            return [];
        }

        $open = [];

        foreach (CalendarConflict::query()
            ->whereIn('calendar_event_id', $eventIds)->whereNull('undone_at')
            ->where('created_at', '>', Carbon::now()->subDays(CalendarConflict::KEEP_DAYS))
            ->orderBy('id')
            ->get() as $conflict) {
            $open[(int) $conflict->calendar_event_id] = $conflict;
        }

        return $open;
    }

    /**
     * Deletions in the tool that removed a REOS change, still undoable.
     *
     * @return list<CalendarConflict>
     */
    public function removed(User $user): array
    {
        return array_values(CalendarConflict::query()
            ->where('user_id', $user->id)->whereNull('calendar_event_id')->whereNull('undone_at')
            ->where('created_at', '>', Carbon::now()->subDays(CalendarConflict::KEEP_DAYS))
            ->latest('id')
            ->limit(20)
            ->get()
            ->all());
    }

    /**
     * Bring the losing version back: it becomes the newest change and is
     * written to the tool.
     *
     * @throws CalendarRefused
     */
    public function undo(CalendarConflict $conflict, CalendarEvents $events): ?CalendarEvent
    {
        if (! $conflict->open()) {
            throw new CalendarRefused('calendar.errors.undoExpired');
        }

        $losing = $conflict->losing;
        $values = array_filter([
            'title' => $losing['title'] ?? null,
            'starts_at' => isset($losing['starts_at']) ? CarbonImmutable::parse($losing['starts_at']) : null,
            'ends_at' => isset($losing['ends_at']) ? CarbonImmutable::parse($losing['ends_at']) : null,
            'all_day' => $losing['all_day'] ?? null,
            'location' => $losing['location'] ?? null,
            'description' => $losing['description'] ?? null,
            'attendees' => $losing['attendees'] ?? null,
        ], static fn (mixed $value): bool => $value !== null);
        $event = $conflict->event;

        if ($event === null) {
            // Deleted in the tool: made again, as a REOS event.
            $event = $events->create(User::query()->findOrFail($conflict->user_id), [
                ...$values,
                'kind' => in_array($losing['kind'] ?? null, CalendarEvent::CHOSEN_KINDS, true) ? $losing['kind'] : 'appointment',
            ]);
        } elseif (($losing['deleted'] ?? false) === true) {
            $events->delete($event);
            $event = null;
        } else {
            $event = $events->update($event, [
                // A field the losing version didn't have is cleared, not kept.
                'location' => null,
                'description' => null,
                'attendees' => [],
                ...$values,
            ]);
        }

        $conflict->forceFill(['undone_at' => Carbon::now()])->save();

        return $event;
    }

    /** Older than 30 days: gone for good. */
    public function prune(): int
    {
        $deleted = CalendarConflict::query()->where('created_at', '<=', Carbon::now()->subDays(CalendarConflict::KEEP_DAYS))->delete();

        return is_int($deleted) ? $deleted : 0;
    }
}
