<?php

declare(strict_types=1);

namespace App\Http\Requests\Feedback;

use Illuminate\Foundation\Http\FormRequest;

/** Rating the other side's agent after a verified closing (A13.3b). */
final class RateAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'deal' => ['required', 'uuid'],
            'agent' => ['required', 'integer'],
            'communication' => ['required', 'integer', 'min:1', 'max:5'],
            'responsiveness' => ['required', 'integer', 'min:1', 'max:5'],
            'professionalism' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array{communication: int, responsiveness: int, professionalism: int} */
    public function scores(): array
    {
        return ['communication' => $this->integer('communication'), 'responsiveness' => $this->integer('responsiveness'), 'professionalism' => $this->integer('professionalism')];
    }

    public function comment(): ?string
    {
        $comment = $this->input('comment');

        return is_string($comment) ? $comment : null;
    }
}
