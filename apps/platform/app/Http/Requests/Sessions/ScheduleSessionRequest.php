<?php

declare(strict_types=1);

namespace App\Http\Requests\Sessions;

use App\Models\VideoSession;
use App\Video\VideoSessions;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Scheduling a 1-on-1 session or a live class (B6.3). */
final class ScheduleSessionRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in([VideoSession::ONE_ON_ONE, VideoSession::LIVE_CLASS])],
            'title' => ['required', 'string', 'max:120'],
            'agenda' => ['nullable', 'string', 'max:2000'],
            /**
             * The start, with its time zone.
             *
             * @example 2026-11-24T15:00:00-06:00
             */
            'starts_at' => ['required', 'date'],
            /** Length in minutes. */
            'minutes' => ['required', 'integer', Rule::in(VideoSessions::LENGTHS)],
            /** People to invite (from the people you work with). */
            'guests' => ['required', 'array', 'min:1', 'max:'.VideoSession::MAX_GUESTS],
            'guests.*' => ['integer', 'distinct'],
        ];
    }

    /** @return array{kind: string, title: string, agenda: string|null, starts_at: CarbonImmutable, minutes: int, guests: list<int>} */
    public function plan(): array
    {
        $agenda = $this->input('agenda');
        /** @var list<int|string> $guests */
        $guests = (array) $this->input('guests', []);

        return [
            'kind' => $this->string('kind')->toString(),
            'title' => $this->string('title')->trim()->toString(),
            'agenda' => is_string($agenda) && trim($agenda) !== '' ? trim($agenda) : null,
            'starts_at' => CarbonImmutable::parse($this->string('starts_at')->toString())->utc(),
            'minutes' => $this->integer('minutes'),
            'guests' => array_map(intval(...), $guests),
        ];
    }
}
