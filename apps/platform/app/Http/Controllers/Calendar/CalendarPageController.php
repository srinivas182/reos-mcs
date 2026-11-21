<?php

declare(strict_types=1);

namespace App\Http\Controllers\Calendar;

use App\Http\Controllers\Controller;
use App\Http\Requests\Calendar\SaveCalendarEventRequest;
use App\Models\CalendarConflict;
use App\Models\CalendarEvent;
use App\Models\User;
use App\Services\Calendar\CalendarConflicts;
use App\Services\Calendar\CalendarEvents;
use App\Services\Calendar\CalendarRefused;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * The calendar on the website (agent dashboard, screen 12): add, change and
 * delete appointments, Try again, and Undo a conflict. Same rules as the API
 * (`Api\Calendar\CalendarController`); each answer goes back to the page.
 */
final class CalendarPageController extends Controller
{
    public function store(SaveCalendarEventRequest $request, #[CurrentUser] User $user, CalendarEvents $events): RedirectResponse
    {
        $event = $events->create($user, $request->values());
        $target = $events->target($user);

        return $this->done($target === null ? __('calendar.savedLocal') : __('calendar.saved', ['title' => $event->title]));
    }

    public function update(SaveCalendarEventRequest $request, #[CurrentUser] User $user, int $event, CalendarEvents $events): RedirectResponse
    {
        $this->refusing(fn () => $events->update($this->find($user, $event), $request->values()));

        return $this->done(__('calendar.updated'));
    }

    public function destroy(#[CurrentUser] User $user, int $event, CalendarEvents $events): RedirectResponse
    {
        $this->refusing(fn () => $events->delete($this->find($user, $event)));

        // The page shows "Deleted" with Undo itself.
        return back();
    }

    public function restore(#[CurrentUser] User $user, int $event, CalendarEvents $events): RedirectResponse
    {
        $found = $this->find($user, $event, trashed: true);
        abort_unless($found->trashed(), 404);
        $events->restore($found);

        return back();
    }

    public function retry(#[CurrentUser] User $user, int $event, CalendarEvents $events): RedirectResponse
    {
        $events->retry($this->find($user, $event));

        return $this->done(__('calendar.retrying'));
    }

    public function undo(#[CurrentUser] User $user, CalendarConflict $conflict, CalendarConflicts $conflicts, CalendarEvents $events): RedirectResponse
    {
        abort_unless($conflict->user_id === $user->id, 404);
        $this->refusing(fn () => $conflicts->undo($conflict, $events));

        return $this->done(__('calendar.conflict.undone'));
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
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
