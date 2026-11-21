<?php

declare(strict_types=1);

namespace App\Http\Requests\Voice;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

/** What someone said, to turn into to-dos (B4.3). */
final class ParseVoiceRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|In|string>> */
    public function rules(): array
    {
        return [
            /** The words, as the phone's speech recognition heard them. */
            'text' => ['required', 'string', 'max:1000'],
            /** `en` or `es`. */
            'language' => ['required', Rule::in(['en', 'es'])],
            /** The phone's time zone, for example `America/Chicago`. */
            'timezone' => ['required', 'timezone:all'],
            /** When it was said, if not just now (a capture queued while offline). */
            'said_at' => ['sometimes', 'nullable', 'date', 'before_or_equal:+5 minutes', 'after:-7 days'],
        ];
    }

    /** @return 'en'|'es' */
    public function language(): string
    {
        return $this->string('language')->toString() === 'es' ? 'es' : 'en';
    }

    /** When it was said, in the speaker's time zone. */
    public function saidAt(): CarbonImmutable
    {
        $zone = $this->string('timezone')->toString();

        return ($this->filled('said_at') ? CarbonImmutable::parse($this->string('said_at')->toString()) : CarbonImmutable::now())->setTimezone($zone);
    }
}
