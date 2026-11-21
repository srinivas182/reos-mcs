<?php

declare(strict_types=1);

namespace App\Http\Requests\Calendar;

use App\Models\CalendarEvent;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

/**
 * An appointment made or changed in REOS ("New appointment" sheet). Timed
 * events send `starts_at` and `ends_at`; all-day events send `start_date`
 * and `end_date` (the last day, inclusive).
 */
final class SaveCalendarEventRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|In|string>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            /** What it is: `appointment`, `showing`, `deadline`, `session` or `ce`. */
            'kind' => ['sometimes', Rule::in(array_values(array_diff(CalendarEvent::KINDS, ['event'])))],
            'all_day' => ['sometimes', 'boolean'],
            /** Start, with its time zone offset (timed events). */
            'starts_at' => ['exclude_if:all_day,true', 'required', 'date'],
            /** End, after the start (timed events). */
            'ends_at' => ['exclude_if:all_day,true', 'required', 'date', 'after:starts_at', 'before_or_equal:'.$this->latestEnd()],
            /** First day, `YYYY-MM-DD` (all-day events). */
            'start_date' => ['exclude_unless:all_day,true', 'required', 'date_format:Y-m-d'],
            /** Last day, inclusive (all-day events). */
            'end_date' => ['exclude_unless:all_day,true', 'required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'location' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'description' => ['sometimes', 'nullable', 'string', 'max:8000'],
            /** Guest emails. */
            'attendees' => ['sometimes', 'array', 'max:50'],
            'attendees.*' => ['string', 'email:rfc', 'max:254'],
            /** Let the calendar email the guests an invitation. Off unless asked: REOS never sends messages for you. */
            'notify_guests' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, mixed> What `CalendarEvents` saves. */
    public function values(): array
    {
        $allDay = $this->boolean('all_day');
        $values = [
            'title' => trim($this->string('title')->toString()),
            'all_day' => $allDay,
            'starts_at' => $allDay
                ? CarbonImmutable::createFromFormat('!Y-m-d', $this->string('start_date')->toString(), 'UTC')
                : CarbonImmutable::parse($this->string('starts_at')->toString())->utc(),
            // All-day events end at midnight after the last day, as calendars store them.
            'ends_at' => $allDay
                ? CarbonImmutable::createFromFormat('!Y-m-d', $this->string('end_date')->toString(), 'UTC')?->addDay()
                : CarbonImmutable::parse($this->string('ends_at')->toString())->utc(),
        ];

        foreach (['kind', 'location', 'description', 'attendees', 'notify_guests'] as $field) {
            if ($this->has($field)) {
                $values[$field] = match ($field) {
                    'notify_guests' => $this->boolean($field),
                    'attendees' => array_values(array_filter((array) $this->input($field), is_string(...))),
                    default => $this->filled($field) ? trim((string) $this->string($field)) : null,
                };
            }
        }

        return $values;
    }

    /** Events up to 14 days long. */
    private function latestEnd(): string
    {
        $start = $this->input('starts_at');

        try {
            return is_string($start) ? CarbonImmutable::parse($start)->addDays(14)->toIso8601String() : '9999-12-31';
        } catch (\Throwable) {
            return '9999-12-31';
        }
    }
}
