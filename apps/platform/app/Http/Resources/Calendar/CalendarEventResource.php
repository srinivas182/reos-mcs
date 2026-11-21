<?php

declare(strict_types=1);

namespace App\Http\Resources\Calendar;

use App\Connections\Calendar\CalendarChoices;
use App\Integrations\Catalogue;
use App\Models\CalendarConflict;
use App\Models\CalendarEvent;
use App\Services\Calendar\CalendarConflicts;
use App\Services\Calendar\CalendarEvents;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One event on a person's REOS calendar, with where it came from, whether
 * it's in step with the tool, and any conflict still undoable.
 *
 * @property CalendarEvent $resource
 */
final class CalendarEventResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $event = $this->resource;
        $events = app(CalendarEvents::class);
        $conflict = $event->relationLoaded('openConflict')
            ? $event->getRelation('openConflict')
            : app(CalendarConflicts::class)->openFor($event);
        $calendar = $event->connection !== null && $event->calendar_id !== null ? CalendarChoices::find($event->connection, $event->calendar_id) : null;

        return [
            'id' => $event->id,
            /** Null for an event from a calendar shared as busy only. */
            'title' => $event->title,
            /**
             * @var 'appointment'|'showing'|'deadline'|'session'|'ce'|'event'
             */
            'kind' => $event->kind,
            'starts_at' => $event->starts_at->toIso8601String(),
            'ends_at' => $event->ends_at->toIso8601String(),
            'all_day' => $event->all_day,
            /** All-day events: the first day, `YYYY-MM-DD`. */
            'start_date' => $event->all_day ? $event->starts_at->copy()->utc()->toDateString() : null,
            /**
             * All-day events: the last day, inclusive.
             *
             * @var string|null
             */
            'end_date' => $event->all_day ? $event->ends_at->copy()->utc()->subDay()->toDateString() : null,
            'location' => $event->location,
            'description' => $event->description,
            /** @var list<string> */
            'attendees' => $event->attendees ?? [],
            'notify_guests' => $event->notify_guests,
            /** From a calendar shared as busy only: times and nothing else. */
            'busy' => $event->busy,
            /** `reos` when made in REOS, otherwise the tool's register id. */
            'source' => $event->source,
            /** The tool it lives in now ("Google Calendar"), or null when only in REOS. */
            'tool' => $event->connection === null ? null : (app(Catalogue::class)->find($event->connection->integration_id)->name ?? $event->connection->integration_id),
            /** The calendar it lives in at the tool. */
            'calendar' => $calendar?->name,
            /** Opens it in the tool. */
            'link' => $event->html_link,
            /**
             * It can be changed in REOS (not busy only, and the calendar allows it).
             *
             * @var bool
             */
            'editable' => $events->editable($event),
            /**
             * Only events made in REOS can be deleted from REOS.
             *
             * @var bool
             */
            'deletable' => $events->deletable($event),
            /**
             * `synced` (in step with the tool), `pending` (being written),
             * `failed` (not written; Try again) or `local` (only in REOS).
             *
             * @var 'synced'|'pending'|'failed'|'local'
             */
            'sync' => match (true) {
                $event->push_state === CalendarEvent::PUSH_FAILED => 'failed',
                $event->push_state === CalendarEvent::PUSH_PENDING => 'pending',
                $event->connection_id === null => 'local',
                default => 'synced',
            },
            /** Translation key for why writing failed. */
            'sync_error' => $event->push_error,
            /**
             * Changed in REOS and in the tool: which version was kept, and the other one, which Undo brings back.
             *
             * @var array{id: int, kept: 'reos'|'tool', reason: 'edited_both'|'deleted_in_reos', losing: array{title: string|null, starts_at: string|null, ends_at: string|null, all_day: bool, location: string|null, deleted: bool}, at: string, until: string}|null
             */
            'conflict' => $conflict instanceof CalendarConflict ? self::conflict($conflict) : null,
        ];
    }

    /** @return array<string, mixed> */
    public static function conflict(CalendarConflict $conflict): array
    {
        $losing = $conflict->losing;

        return [
            'id' => $conflict->id,
            'kept' => $conflict->kept,
            'reason' => $conflict->reason,
            'losing' => [
                'title' => $losing['title'] ?? null,
                'starts_at' => $losing['starts_at'] ?? null,
                'ends_at' => $losing['ends_at'] ?? null,
                'all_day' => (bool) ($losing['all_day'] ?? false),
                'location' => $losing['location'] ?? null,
                'deleted' => (bool) ($losing['deleted'] ?? false),
            ],
            'at' => $conflict->created_at?->toIso8601String(),
            'until' => $conflict->expiresAt()->toIso8601String(),
        ];
    }
}
