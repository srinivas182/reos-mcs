<?php

declare(strict_types=1);

namespace App\Http\Requests\Bookings;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

/** Book a 1-on-1 session (A10.2a, candidate screen 18). */
final class BookSessionRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|In|string>> */
    public function rules(): array
    {
        return [
            /** The instructor's or mentor's user id. */
            'instructor' => ['required', 'integer'],
            /** A start from the instructor's free slots (ISO 8601). */
            'starts_at' => ['required', 'date'],
            /**
             * 30 or 60.
             *
             * @var int
             */
            'minutes' => ['required', 'integer', Rule::in([30, 60])],
            /** What the student wants help with. */
            'topic' => ['required', 'string', 'min:3', 'max:200'],
        ];
    }

    public function startsAt(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->string('starts_at')->toString())->utc();
    }
}
