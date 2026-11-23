<?php

declare(strict_types=1);

namespace App\Http\Requests\Connections;

use App\Connections\Calendar\CalendarMode;
use App\Connections\HistoryRange;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

/** "What to sync" (Connections and Sync journey, screens 7 and 16). */
final class UpdateConnectionRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|Enum|string>> */
    public function rules(): array
    {
        return [
            /** How far back to sync: `3m`, `12m` or `all`. */
            'history' => ['sometimes', 'required', Rule::enum(HistoryRange::class)],
            /**
             * Calendars only: each calendar id → `full` (read and write), `busy` (times only) or `off`.
             *
             * @var array<string, 'full'|'busy'|'off'>
             */
            'calendars' => ['sometimes', 'array', 'max:100'],
            'calendars.*' => [Rule::enum(CalendarMode::class)],
            /** Calendars only: where events made in REOS go, or an empty string to keep them in REOS. */
            'write_calendar' => ['sometimes', 'nullable', 'string', 'max:255'],
            /**
             * dotloop only: the profiles whose loops sync (at least one).
             *
             * @var list<string>
             */
            'profiles' => ['sometimes', 'array', 'min:1', 'max:50'],
            'profiles.*' => ['string', 'max:64'],
        ];
    }

    /** @return list<Closure(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->has('history') && ! $this->choosesCalendars() && ! $this->has('profiles')) {
                    $validator->errors()->add('history', __('validation.required', ['attribute' => 'history']));
                }
            },
        ];
    }

    public function history(): ?HistoryRange
    {
        return $this->filled('history') ? HistoryRange::from($this->string('history')->toString()) : null;
    }

    /** @return list<string>|null the chosen dotloop profiles, or null to leave them */
    public function profiles(): ?array
    {
        if (! $this->has('profiles')) {
            return null;
        }

        return array_values(array_unique(array_filter((array) $this->input('profiles', []), is_string(...))));
    }

    public function choosesCalendars(): bool
    {
        return $this->has('calendars') || $this->has('write_calendar');
    }

    /** @return array<string, string> */
    public function calendarModes(): array
    {
        $modes = [];

        foreach ((array) $this->input('calendars', []) as $id => $mode) {
            if (is_string($mode)) {
                $modes[(string) $id] = $mode;
            }
        }

        return $modes;
    }

    /** A calendar id, '' for none, or null to leave it as it is. */
    public function writeCalendar(): ?string
    {
        if (! $this->has('write_calendar')) {
            return null;
        }

        $value = $this->input('write_calendar');

        return is_string($value) ? $value : '';
    }
}
