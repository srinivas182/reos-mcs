<?php

declare(strict_types=1);

namespace App\Http\Requests\Learn;

use App\Models\Lesson;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

/** A search in my courses (A5.3b): the words, and optionally one language. */
final class SearchRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|In|string>> */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:300'],
            'language' => ['nullable', Rule::in(Lesson::LANGUAGES)],
        ];
    }

    public function language(): ?string
    {
        $language = $this->input('language');

        return is_string($language) ? $language : null;
    }
}
