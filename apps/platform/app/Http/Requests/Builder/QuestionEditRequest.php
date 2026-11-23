<?php

declare(strict_types=1);

namespace App\Http\Requests\Builder;

use App\Content\Review\QuestionBank;
use App\Enums\QuizTopic;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\In;

/**
 * An edit to a question on screen 45 (A8.1; the starter bank, A9.1): wording
 * in both languages, the right answer, topic, difficulty and exam portion.
 */
final class QuestionEditRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|string|In|Enum>> */
    public function rules(): array
    {
        $language = static fn (string $l): array => [
            "{$l}.prompt" => ['required', 'string', 'min:5', 'max:400'],
            "{$l}.options" => ['required', 'array', 'min:2', 'max:5'],
            "{$l}.options.*" => ['required', 'string', 'max:200'],
            "{$l}.explanation" => ['nullable', 'string', 'max:400'],
        ];

        return [
            ...$language('en'),
            ...$language('es'),
            'correct' => ['required', 'integer', 'min:0', 'max:4'],
            'topic' => ['required', Rule::enum(QuizTopic::class)],
            'difficulty' => ['required', Rule::in(QuestionBank::DIFFICULTIES)],
            'portion' => ['required', Rule::in(QuestionBank::PORTIONS)],
        ];
    }

    /**
     * The validated edit, typed.
     *
     * @return array{en: array{prompt: string, options: list<string>, explanation: string}, es: array{prompt: string, options: list<string>, explanation: string}, correct: int, topic: string, difficulty: string, portion: string}
     */
    public function edit(): array
    {
        $data = $this->validated();
        $language = static function (mixed $v): array {
            $v = is_array($v) ? $v : [];

            return ['prompt' => is_string($v['prompt'] ?? null) ? $v['prompt'] : '', 'options' => array_values(array_filter((array) ($v['options'] ?? []), is_string(...))), 'explanation' => is_string($v['explanation'] ?? null) ? $v['explanation'] : ''];
        };

        return [
            'en' => $language($data['en'] ?? null), 'es' => $language($data['es'] ?? null),
            'correct' => is_numeric($data['correct'] ?? null) ? (int) $data['correct'] : 0,
            'topic' => is_string($data['topic'] ?? null) ? $data['topic'] : '', 'difficulty' => is_string($data['difficulty'] ?? null) ? $data['difficulty'] : '', 'portion' => is_string($data['portion'] ?? null) ? $data['portion'] : '',
        ];
    }
}
