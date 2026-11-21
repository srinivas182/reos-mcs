<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Onboarding;

use App\Enums\Locale;
use App\Enums\OnboardingPath;
use App\Models\State;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\In;

final class OnboardingRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|Enum|Exists|In|string>> */
    public function rules(): array
    {
        return [
            'path' => ['sometimes', 'nullable', Rule::enum(OnboardingPath::class)],
            /** Two-letter code of a live state. */
            'state' => ['sometimes', 'nullable', 'string', 'size:2', Rule::exists('states', 'code')->where('status', 'live')],
            /** YYYY-MM, or null for "Not sure". */
            'exam_month' => ['sometimes', 'nullable', 'date_format:Y-m', 'after_or_equal:'.now()->format('Y-m')],
            /** Minutes a day: 10, 20, 30, 45 or 60 ("1 h+"). */
            'minutes_per_day' => ['sometimes', 'nullable', 'integer', 'min:10', 'max:60', 'multiple_of:5'],
            'locale' => ['sometimes', 'nullable', Rule::enum(Locale::class)],
            /** HH:MM, or null for no reminder. */
            'reminder_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            /** True when "Build my plan" is tapped: the setup won't show again. */
            'complete' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array{path?: OnboardingPath|null, state?: State|null, exam_month?: Carbon|null, minutes_per_day?: int|null, locale?: Locale|null, reminder_time?: string|null, complete?: bool}
     */
    public function choices(): array
    {
        $choices = [];

        if ($this->has('path')) {
            $choices['path'] = $this->filled('path') ? OnboardingPath::from($this->string('path')->toString()) : null;
        }
        if ($this->has('state')) {
            $choices['state'] = $this->filled('state') ? State::query()->find($this->string('state')->upper()->toString()) : null;
        }
        if ($this->has('exam_month')) {
            $choices['exam_month'] = $this->filled('exam_month') ? Carbon::createFromFormat('Y-m-d', $this->string('exam_month')->toString().'-01') : null;
        }
        if ($this->has('minutes_per_day')) {
            $choices['minutes_per_day'] = $this->filled('minutes_per_day') ? $this->integer('minutes_per_day') : null;
        }
        if ($this->filled('locale')) {
            $choices['locale'] = Locale::from($this->string('locale')->toString());
        }
        if ($this->has('reminder_time')) {
            $choices['reminder_time'] = $this->filled('reminder_time') ? $this->string('reminder_time')->toString() : null;
        }
        if ($this->has('complete')) {
            $choices['complete'] = $this->boolean('complete');
        }

        return $choices;
    }
}
