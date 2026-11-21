<?php

declare(strict_types=1);

namespace App\Http\Requests\Calendar;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** A window of the calendar, up to 62 days (two months on screen). */
final class CalendarRangeRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|string>> */
    public function rules(): array
    {
        return [
            /** Start of the window, with time zone offset (the device's local midnight for "today"). */
            'from' => ['required', 'date'],
            /** End of the window, after `from`, at most 62 days later. */
            'to' => ['required', 'date', 'after:from', 'before_or_equal:'.$this->latest()],
        ];
    }

    public function from(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->string('from')->toString())->utc();
    }

    public function to(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->string('to')->toString())->utc();
    }

    private function latest(): string
    {
        $from = $this->input('from');

        try {
            return is_string($from) ? CarbonImmutable::parse($from)->addDays(62)->toIso8601String() : '9999-12-31';
        } catch (\Throwable) {
            return '9999-12-31';
        }
    }
}
