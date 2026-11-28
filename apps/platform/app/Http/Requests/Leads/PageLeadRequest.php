<?php

declare(strict_types=1);

namespace App\Http\Requests\Leads;

use Illuminate\Foundation\Http\FormRequest;

/** A message from the agent's public page (B14.3a); `website` is a field people never fill in. */
final class PageLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['nullable', 'required_without:phone', 'string', 'email', 'max:160'],
            'phone' => ['nullable', 'required_without:email', 'string', 'regex:/^\+?[0-9 ().-]{7,30}$/'],
            'message' => ['required', 'string', 'min:2', 'max:1500'],
            'has_agent' => ['sometimes', 'boolean'],
            'consent' => ['accepted'],
            'website' => ['prohibited'],
        ];
    }
}
