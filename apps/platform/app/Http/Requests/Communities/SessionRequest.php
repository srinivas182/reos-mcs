<?php

declare(strict_types=1);

namespace App\Http\Requests\Communities;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/** An expert session (B17.2): online or in person, free or (on Partner) paid, with a CE course number only if approved elsewhere. */
final class SessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:160'],
            'about' => ['nullable', 'string', 'max:2000'],
            'format' => ['required', 'string', 'in:online,in_person'],
            'starts_at' => ['required', 'date', 'after:now'],
            'minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'place' => ['nullable', 'required_if:format,in_person', 'string', 'max:200'],
            'join_url' => ['nullable', 'required_if:format,online', 'url:https', 'max:500'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'price_cents' => ['required', 'integer', 'min:0', 'max:100000'],
            'ce_course' => ['nullable', 'string', 'max:40'],
        ];
    }

    /** @return array{title: string, about: string|null, format: string, starts_at: CarbonImmutable, minutes: int, place: string|null, join_url: string|null, capacity: int|null, price_cents: int, ce_course: string|null} */
    public function values(): array
    {
        $text = fn (string $key): ?string => $this->filled($key) ? $this->string($key)->trim()->toString() : null;

        return [
            'title' => $this->string('title')->trim()->toString(), 'about' => $text('about'), 'format' => $this->string('format')->toString(),
            'starts_at' => CarbonImmutable::parse($this->string('starts_at')->toString()), 'minutes' => $this->integer('minutes'),
            'place' => $text('place'), 'join_url' => $text('join_url'), 'capacity' => $this->filled('capacity') ? $this->integer('capacity') : null,
            'price_cents' => $this->integer('price_cents'), 'ce_course' => $text('ce_course'),
        ];
    }
}
