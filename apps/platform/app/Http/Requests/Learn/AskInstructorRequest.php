<?php

declare(strict_types=1);

namespace App\Http\Requests\Learn;

use App\Content\Player\AskInstructor;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\In;

/** A question asked mid-lesson (Interrupt and ask). */
final class AskInstructorRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|In|string>> */
    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'min:2', 'max:500'],
            /** ask, simpler, spanish or quiz. */
            'mode' => ['nullable', new In(AskInstructor::MODES)],
            /** The answer being explained again (simpler, spanish). */
            'previous' => ['nullable', 'string', 'max:2000'],
            /** Where the video was paused, in seconds. */
            'at' => ['nullable', 'integer', 'min:0', 'max:86400'],
        ];
    }

    public function question(): string
    {
        return trim($this->string('question')->toString());
    }

    public function mode(): string
    {
        return $this->filled('mode') ? $this->string('mode')->toString() : 'ask';
    }

    public function previous(): ?string
    {
        return $this->filled('previous') ? $this->string('previous')->toString() : null;
    }
}
