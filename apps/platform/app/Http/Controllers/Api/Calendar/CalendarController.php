<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Calendar;

use App\Http\Controllers\Controller;
use App\Http\Requests\Calendar\CalendarRangeRequest;
use App\Http\Requests\Calendar\SaveCalendarEventRequest;
use App\Http\Resources\Calendar\CalendarEventResource;
use App\Models\CalendarConflict;
use App\Models\CalendarEvent;
use App\Models\User;
use App\Services\Calendar\CalendarConflicts;
use App\Services\Calendar\CalendarEvents;
use App\Services\Calendar\CalendarRefused;
use App\Services\Calendar\CalendarView;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/**
 * Your REOS calendar (B4.1, ADR 0017): appointments, showings, deadlines,
 * sessions and CE dates made in REOS, and events from your connected
 * calendars. Changes are written back to the calendar tool in the background.
 */
final class CalendarController extends Controller
{
    /**
     * List events.
     *
     * Events overlapping the window, earliest first, plus where new events
     * go and any events deleted in your calendar tool that you can bring back.
     *
     * @response array{data: list<CalendarEventResource>, write_to: array{connection: int, tool: string, account: string|null, calendar: string}|null, removed: list<array{conflict: int, title: string|null, starts_at: string|null, at: string|null}>}
     */
    public function index(CalendarRangeRequest $request, #[CurrentUser] User $user, CalendarView $view): JsonResponse
    {
        return response()->json($view->window($user, $request->from(), $request->to()));
    }

    /**
     * Add an event.
     *
     * Saved at once and written to the calendar you chose (Connections ›
     * Manage); without one it stays in REOS.
     *
     * @status 201
     */
    public function store(SaveCalendarEventRequest $request, #[CurrentUser] User $user, CalendarEvents $events): CalendarEventResource
    {
        return new CalendarEventResource($events->create($user, $request->values()));
    }

    /**
     * Change an event.
     *
     * Events from a calendar shared as busy only, or one you can't edit,
     * can't be changed here.
     */
    public function update(SaveCalendarEventRequest $request, #[CurrentUser] User $user, int $event, CalendarEvents $events): CalendarEventResource
    {
        return new CalendarEventResource($this->refusing(fn () => $events->update($this->find($user, $event), $request->values())));
    }

    /**
     * Delete an event.
     *
     * Only events made in REOS; it's removed from the calendar tool too.
     * Restore undoes it.
     */
    public function destroy(#[CurrentUser] User $user, int $event, CalendarEvents $events): Response
    {
        $this->refusing(fn () => $events->delete($this->find($user, $event)));

        return response()->noContent();
    }

    /** Restore a deleted event (Undo). */
    public function restore(#[CurrentUser] User $user, int $event, CalendarEvents $events): CalendarEventResource
    {
        $found = $this->find($user, $event, trashed: true);
        abort_unless($found->trashed(), 404);

        return new CalendarEventResource($events->restore($found));
    }

    /**
     * Try again.
     *
     * Writes an event to the calendar tool again after writing failed.
     *
     * @status 202
     */
    public function retry(#[CurrentUser] User $user, int $event, CalendarEvents $events): JsonResponse
    {
        $events->retry($this->find($user, $event));

        return response()->json(['queued' => true], 202);
    }

    /**
     * Undo a conflict.
     *
     * Brings back the version that lost when an event changed in REOS and in
     * the calendar tool; it becomes the newest change. Returns the event, or
     * no content when undoing deleted it.
     */
    public function undo(#[CurrentUser] User $user, CalendarConflict $conflict, CalendarConflicts $conflicts, CalendarEvents $events): CalendarEventResource|Response
    {
        abort_unless($conflict->user_id === $user->id, 404);
        $event = $this->refusing(fn () => $conflicts->undo($conflict, $events));

        return $event === null ? response()->noContent() : new CalendarEventResource($event);
    }

    private function find(User $user, int $id, bool $trashed = false): CalendarEvent
    {
        $query = $trashed ? CalendarEvent::withTrashed() : CalendarEvent::query();

        return $query->with('connection')->where('user_id', $user->id)->findOrFail($id);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $change
     * @return T
     */
    private function refusing(callable $change): mixed
    {
        try {
            return $change();
        } catch (CalendarRefused $e) {
            throw ValidationException::withMessages(['event' => __($e->reason)]);
        }
    }
}
