<?php

declare(strict_types=1);

namespace App\Http\Requests\Practice;

use App\Practice\DiagnosticBank;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

/** Save as flashcard (A9.2d): from a check-in, an Ask answer, or answer review. */
final class SaveFlashcardRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|string|In>> */
    public function rules(): array
    {
        return [
            'source' => ['required', Rule::in(['checkin', 'ask', 'review'])],
            'front' => ['required', 'string', 'min:3', 'max:500'],
            'back' => ['required', 'string', 'min:1', 'max:1000'],
            'topic' => ['nullable', Rule::in(array_keys(DiagnosticBank::WEIGHTS))],
            'question_id' => ['nullable', 'integer', 'exists:quiz_questions,id'],
        ];
    }

    public function text(string $key): string
    {
        $value = $this->validated($key);

        return is_string($value) ? $value : '';
    }

    public function topic(): ?string
    {
        $topic = $this->validated('topic');

        return is_string($topic) ? $topic : null;
    }

    public function questionId(): ?int
    {
        $id = $this->validated('question_id');

        return is_numeric($id) ? (int) $id : null;
    }
}
