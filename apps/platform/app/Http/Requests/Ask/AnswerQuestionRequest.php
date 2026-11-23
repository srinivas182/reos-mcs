<?php

declare(strict_types=1);

namespace App\Http\Requests\Ask;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** An instructor's answer to a student's question (A10.1b, screen i10). */
final class AnswerQuestionRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|string>> */
    public function rules(): array
    {
        return [
            'answer' => ['required', 'string', 'min:2', 'max:4000'],
            /** File the answer with the course so Ask can use it for the next student. */
            'add_to_knowledge' => ['sometimes', 'boolean'],
            /** Add it to the student's flashcards. */
            'flashcard' => ['sometimes', 'boolean'],
        ];
    }
}
