<?php

declare(strict_types=1);

namespace App\Http\Requests\Sessions;

use App\Enums\QuizTopic;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A quick action in the room (B6.3b): share a lesson, or send practice questions on a topic. */
final class ShareRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(['lesson', 'questions'])],
            'lesson' => ['required_if:kind,lesson', 'nullable', 'integer'],
            'topic' => ['required_if:kind,questions', 'nullable', Rule::enum(QuizTopic::class)],
        ];
    }
}
