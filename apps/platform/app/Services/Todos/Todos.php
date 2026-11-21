<?php

declare(strict_types=1);

namespace App\Services\Todos;

use App\Models\CalendarEvent;
use App\Models\Todo;
use App\Models\User;
use App\Services\Calendar\CalendarEvents;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A person's to-dos (B4.3, ADR 0018), typed or spoken.
 *
 * - Saving the same capture twice (the phone retries after being offline)
 *   saves it once: the phone names each new to-do (`client_id`).
 * - A due date is also an event on the person's REOS calendar (kind
 *   `todo`), so it reaches Google or Outlook. Timed to-dos take 15 minutes;
 *   dated ones are all day. Changing or removing the due date changes the
 *   event; deleting the to-do deletes it. Done to-dos keep theirs.
 * - Reminders are set on the phone; the server keeps `remind_at` so every
 *   device knows (server push arrives with B6.2).
 */
final readonly class Todos
{
    public const FIELDS = ['title', 'notes', 'due_at', 'due_date', 'remind_at', 'related_type', 'related_id', 'related_label', 'source'];

    private const TIMED_MINUTES = 15;

    public function __construct(private CalendarEvents $calendar) {}

    /**
     * Open to-dos first by due (undated last), or done ones newest first.
     *
     * @return Collection<int, Todo>
     */
    public function list(User $user, string $status): Collection
    {
        $query = Todo::query()->where('user_id', $user->id);

        if ($status === 'done') {
            return $query->whereNotNull('done_at')->latest('done_at')->limit(200)->get();
        }

        if ($status === 'open') {
            $query->whereNull('done_at');
        }

        return $query
            ->orderByRaw('case when due_at is null and due_date is null then 1 else 0 end')
            ->orderByRaw('coalesce(due_at, due_date)')
            ->orderBy('id')
            ->limit(200)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array{0: Todo, 1: bool} The to-do, and whether it is new.
     */
    public function create(User $user, array $values, ?string $clientId): array
    {
        if ($clientId !== null) {
            $existing = Todo::withTrashed()->where('user_id', $user->id)->where('client_id', $clientId)->first();

            if ($existing !== null) {
                return [$existing, false];
            }
        }

        $todo = DB::transaction(function () use ($user, $values, $clientId): Todo {
            $todo = new Todo(['user_id' => $user->id, 'client_id' => $clientId]);
            $todo->forceFill(self::only($values))->save();

            return $todo;
        });

        $this->toCalendar($todo);

        return [$todo, true];
    }

    /** @param  array<string, mixed>  $values */
    public function update(Todo $todo, array $values): Todo
    {
        $todo->forceFill(self::only($values));
        $dueChanged = $todo->isDirty(['due_at', 'due_date', 'title']);
        $todo->save();

        if ($dueChanged) {
            $this->toCalendar($todo);
        }

        return $todo;
    }

    public function complete(Todo $todo, bool $done = true): Todo
    {
        $todo->forceFill(['done_at' => $done ? ($todo->done_at ?? Carbon::now()) : null])->save();

        return $todo;
    }

    public function delete(Todo $todo): void
    {
        $todo->delete();
        $this->toCalendar($todo);
    }

    public function restore(Todo $todo): Todo
    {
        $todo->restore();
        $this->toCalendar($todo);

        return $todo;
    }

    /** Puts the due date on the calendar, moves it, or takes it off. */
    private function toCalendar(Todo $todo): void
    {
        $event = $todo->calendar_event_id === null ? null : CalendarEvent::withTrashed()->find($todo->calendar_event_id);
        $user = User::query()->find($todo->user_id);
        $due = $todo->due_at ?? $todo->due_date;

        if ($user === null) {
            return;
        }

        if ($todo->trashed() || $due === null) {
            if ($event !== null && ! $event->trashed()) {
                $this->calendar->delete($event);
            }

            return;
        }

        $values = ['title' => $todo->title, 'kind' => 'todo', 'related_type' => 'todo', 'related_id' => (string) $todo->id, ...self::when($todo)];

        if ($event === null) {
            $event = $this->calendar->create($user, $values);
            $todo->forceFill(['calendar_event_id' => $event->id])->saveQuietly();

            return;
        }

        if ($event->trashed()) {
            $event = $this->calendar->restore($event);
        }

        $this->calendar->update($event, $values);
    }

    /** @return array{starts_at: CarbonImmutable, ends_at: CarbonImmutable, all_day: bool} */
    private static function when(Todo $todo): array
    {
        if ($todo->due_at !== null) {
            $start = CarbonImmutable::parse($todo->due_at)->utc();

            return ['starts_at' => $start, 'ends_at' => $start->addMinutes(self::TIMED_MINUTES), 'all_day' => false];
        }

        $day = CarbonImmutable::parse(Carbon::parse($todo->due_date)->toDateString(), 'UTC');

        return ['starts_at' => $day, 'ends_at' => $day->addDay(), 'all_day' => true];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private static function only(array $values): array
    {
        $values = array_intersect_key($values, array_flip(self::FIELDS));

        // A to-do is due at a moment or on a day, not both.
        if (($values['due_at'] ?? null) !== null) {
            $values['due_date'] = null;
        } elseif (($values['due_date'] ?? null) !== null) {
            $values['due_at'] = null;
        }

        return $values;
    }
}
