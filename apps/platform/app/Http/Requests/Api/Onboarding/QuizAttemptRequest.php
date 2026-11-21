<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Onboarding;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

final class QuizAttemptRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|Exists|string>> */
    public function rules(): array
    {
        return [
            /** The state the quiz was taken for. */
            'state' => ['nullable', 'string', 'size:2', Rule::exists('states', 'code')],
            /** Each answer given before sign-up, kept on the phone until now. */
            'answers' => ['required', 'array', 'min:1', 'max:20'],
            'answers.*.question_id' => ['required', 'integer', Rule::exists('quiz_questions', 'id')],
            'answers.*.choice' => ['required', 'integer', 'between:0,3'],
        ];
    }

    /** @return list<array{question_id: int, choice: int}> */
    public function answers(): array
    {
        /** @var list<array{question_id: int|string, choice: int|string}> $answers */
        $answers = $this->validated('answers');

        return array_map(static fn (array $a): array => ['question_id' => (int) $a['question_id'], 'choice' => (int) $a['choice']], $answers);
    }
}
