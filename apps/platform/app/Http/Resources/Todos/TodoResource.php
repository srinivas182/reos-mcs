<?php

declare(strict_types=1);

namespace App\Http\Resources\Todos;

use App\Models\Todo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * One to-do (B4.3).
 *
 * @property Todo $resource
 */
final class TodoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $todo = $this->resource;

        return [
            'id' => $todo->id,
            /** The phone's own id for it, when it made it. */
            'client_id' => $todo->client_id,
            'title' => $todo->title,
            'notes' => $todo->notes,
            /** Due at this moment (ISO 8601), or null. */
            'due_at' => $todo->due_at?->toIso8601String(),
            /** Due on this day (`YYYY-MM-DD`), when it has no time. */
            'due_date' => $todo->due_date === null ? null : Carbon::parse($todo->due_date)->toDateString(),
            'remind_at' => $todo->remind_at?->toIso8601String(),
            /**
             * The person it's about.
             *
             * @var array{type: string, id: string|null, label: string|null}|null
             */
            'related' => $todo->related_type === null ? null : ['type' => $todo->related_type, 'id' => $todo->related_id, 'label' => $todo->related_label],
            /**
             * @var 'typed'|'voice'
             */
            'source' => $todo->source,
            'done' => $todo->done(),
            'done_at' => $todo->done_at?->toIso8601String(),
            /** The calendar event its due date made, if any. */
            'calendar_event_id' => $todo->calendar_event_id,
            'created_at' => $todo->created_at?->toIso8601String(),
        ];
    }
}
