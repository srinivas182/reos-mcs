<?php

declare(strict_types=1);

namespace App\Http\Requests\Bookings;

use App\Models\SessionOffer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** An instructor's or mentor's hours, prices and topics for 1-on-1 sessions (A10.2a, screen i14 Hours). */
final class SessionOfferRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|Closure|string>> */
    public function rules(): array
    {
        return [
            'bio' => ['nullable', 'string', 'max:1000'],
            /** @var list<'en'|'es'> */
            'languages' => ['required', 'array', 'min:1'],
            'languages.*' => ['in:en,es'],
            /** @var list<string> */
            'topics' => ['present', 'array', 'max:12'],
            'topics.*' => ['string', 'max:60'],
            /**
             * Weekly hours in `timezone`: each day (`mon`…`sun`) a list of [from, to] in 24-hour "HH:MM", on the half hour.
             *
             * @var array<string, list<list<string>>>
             */
            'hours' => ['present', 'array', function (string $attribute, mixed $value, Closure $fail): void {
                foreach (is_array($value) ? $value : [] as $day => $ranges) {
                    if (! in_array($day, SessionOffer::DAYS, true) || ! is_array($ranges) || count($ranges) > 4) {
                        $fail(__('sessions.booking.errors.hours'));

                        return;
                    }
                    foreach ($ranges as $range) {
                        if (! is_array($range) || count($range) !== 2 || ! is_string($range[0] ?? null) || ! is_string($range[1] ?? null)
                            || preg_match('/^([01]\d|2[0-3]):(00|30)$/', $range[0]) !== 1 || preg_match('/^([01]\d|2[0-4]):(00|30)$/', $range[1]) !== 1 || $range[0] >= $range[1]) {
                            $fail(__('sessions.booking.errors.hours'));

                            return;
                        }
                    }
                }
            }],
            'timezone' => ['required', 'timezone:all'],
            /** Cents; 0 means included (free to book). */
            'price_30_cents' => ['required', 'integer', 'min:0', 'max:100000'],
            'price_60_cents' => ['required', 'integer', 'min:0', 'max:100000'],
            'active' => ['required', 'boolean'],
        ];
    }

    /** @return array{bio: string|null, languages: list<'en'|'es'>, topics: list<string>, hours: array<string, list<array{0: string, 1: string}>>, timezone: string, price_30_cents: int, price_60_cents: int, active: bool} */
    public function values(): array
    {
        $hours = [];
        foreach ((array) $this->input('hours', []) as $day => $ranges) {
            foreach (is_array($ranges) ? $ranges : [] as $range) {
                if (is_array($range) && is_string($range[0] ?? null) && is_string($range[1] ?? null)) {
                    $hours[(string) $day][] = [$range[0], $range[1]];
                }
            }
        }

        return [
            'bio' => $this->filled('bio') ? trim($this->string('bio')->toString()) : null,
            'languages' => array_values(array_filter(array_map(static fn (mixed $l): ?string => match ($l) {
                'en' => 'en', 'es' => 'es', default => null,
            }, (array) $this->input('languages', [])))),
            'topics' => array_values(array_filter(array_map(static fn (mixed $t): ?string => is_string($t) && trim($t) !== '' ? trim($t) : null, (array) $this->input('topics', [])))),
            'hours' => $hours,
            'timezone' => $this->string('timezone')->toString(),
            'price_30_cents' => $this->integer('price_30_cents'),
            'price_60_cents' => $this->integer('price_60_cents'),
            'active' => $this->boolean('active'),
        ];
    }
}
