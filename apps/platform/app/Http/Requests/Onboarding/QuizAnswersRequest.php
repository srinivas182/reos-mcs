<?php

declare(strict_types=1);

namespace App\Http\Requests\Onboarding;

use App\Services\Onboarding\FreeQuiz;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** The free quiz's answers, sent once at the end. */
final class QuizAnswersRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|string>> */
    public function rules(): array
    {
        return [
            'answers' => ['required', 'array', 'min:1', 'max:'.FreeQuiz::LENGTH],
            'answers.*.question_id' => ['required', 'integer', 'distinct', 'exists:quiz_questions,id'],
            'answers.*.choice' => ['required', 'integer', 'min:0', 'max:3'],
        ];
    }

    /** @return list<array{question_id: int, choice: int}> */
    public function answers(): array
    {
        $answers = [];

        // Validated above: whole numbers, sent as numbers or numeric strings.
        foreach ((array) $this->validated('answers') as $answer) {
            if (is_array($answer) && is_numeric($answer['question_id'] ?? null) && is_numeric($answer['choice'] ?? null)) {
                $answers[] = ['question_id' => intval($answer['question_id']), 'choice' => intval($answer['choice'])];
            }
        }

        return $answers;
    }
}
