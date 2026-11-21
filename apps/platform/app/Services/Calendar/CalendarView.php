<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Connections\Calendar\CalendarChoices;
use App\Http\Resources\Calendar\CalendarEventResource;
use App\Integrations\Catalogue;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * The calendar as screens show it (phone Today, web dashboard): events in a
 * window with their conflicts, where new events go, and events deleted in
 * the tool that can still be brought back.
 */
final readonly class CalendarView
{
    public function __construct(
        private CalendarEvents $events,
        private CalendarConflicts $conflicts,
        private Catalogue $catalogue,
    ) {}

    /** @return array{data: list<array<string, mixed>>, write_to: array{connection: int, tool: string, account: string|null, calendar: string}|null, removed: list<array{conflict: int, title: string|null, starts_at: string|null, at: string|null}>} */
    public function window(User $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $events = $this->events->between($user, $from, $to);
        $open = $this->conflicts->openForMany(array_values(array_map(intval(...), $events->modelKeys())));

        foreach ($events as $event) {
            $event->setRelation('openConflict', $open[$event->id] ?? null);
        }

        $target = $this->events->target($user);
        /** @var list<array<string, mixed>> $data */
        $data = array_values(CalendarEventResource::collection($events)->resolve());

        return [
            'data' => $data,
            'write_to' => $target === null ? null : [
                'connection' => $target[0]->id,
                'tool' => $this->catalogue->find($target[0]->integration_id)->name ?? $target[0]->integration_id,
                'account' => $target[0]->account_label,
                'calendar' => CalendarChoices::find($target[0], $target[1])->name ?? $target[1],
            ],
            'removed' => array_map(static fn ($conflict): array => [
                'conflict' => $conflict->id,
                'title' => $conflict->losing['title'] ?? null,
                'starts_at' => $conflict->losing['starts_at'] ?? null,
                'at' => $conflict->created_at?->toIso8601String(),
            ], $this->conflicts->removed($user)),
        ];
    }
}
