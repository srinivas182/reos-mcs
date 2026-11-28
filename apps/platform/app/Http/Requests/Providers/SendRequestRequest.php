<?php

declare(strict_types=1);

namespace App\Http\Requests\Providers;

use Illuminate\Foundation\Http\FormRequest;

/** A request to connect (B16.1): the agent, and an optional short note without contact details. */
final class SendRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'agent_id' => ['required', 'integer', 'min:1'],
            'message' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function message(): ?string
    {
        return $this->filled('message') ? $this->string('message')->toString() : null;
    }
}
