<?php

declare(strict_types=1);

namespace App\Http\Requests\Crm;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\In;

/** "I called" or "I texted" from the agent's own phone (B8.3), logged to the CRMs. */
final class ContactTouchRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|In|string>> */
    public function rules(): array
    {
        return [
            'channel' => ['required', new In(['call', 'text'])],
            /** What was texted. */
            'body' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function channel(): string
    {
        return $this->string('channel')->toString();
    }

    public function body(): ?string
    {
        return $this->filled('body') ? $this->string('body')->toString() : null;
    }
}
