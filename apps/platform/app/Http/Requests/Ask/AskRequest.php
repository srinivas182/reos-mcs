<?php

declare(strict_types=1);

namespace App\Http\Requests\Ask;

use App\Ask\Ask;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

/** A question in Ask (A10.1), or a follow-up on the last answer. */
final class AskRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|In|string>> */
    public function rules(): array
    {
        return [
            /** The question; needed for `ask`, ignored for follow-ups. */
            'question' => ['required_if:mode,ask', 'nullable', 'string', 'min:2', 'max:500'],
            /**
             * `ask` (a new question), or a follow-up on the thread's last answer: `simpler`, `spanish` or `quiz`.
             *
             * @var 'ask'|'simpler'|'spanish'|'quiz'
             */
            'mode' => ['sometimes', Rule::in(Ask::MODES)],
            /** The conversation to continue; a new one when empty. */
            'thread' => ['nullable', 'uuid'],
        ];
    }

    public function question(): string
    {
        return trim($this->string('question')->toString());
    }

    public function mode(): string
    {
        return $this->string('mode', 'ask')->toString();
    }

    public function thread(): ?string
    {
        return $this->filled('thread') ? $this->string('thread')->toString() : null;
    }
}
