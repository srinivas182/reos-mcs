<?php

declare(strict_types=1);

namespace App\Http\Requests\Assistant;

use Illuminate\Foundation\Http\FormRequest;

/** A question to the assistant (A12.3; screen 60), in a new chat or one of mine. */
final class AssistantQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'min:2', 'max:1000'],
            'chat' => ['nullable', 'uuid'],
        ];
    }
}
