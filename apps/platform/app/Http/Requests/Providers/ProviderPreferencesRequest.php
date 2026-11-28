<?php

declare(strict_types=1);

namespace App\Http\Requests\Providers;

use Illuminate\Foundation\Http\FormRequest;

/** Requests from providers (B16.2): on or off altogether, and the categories turned off. */
final class ProviderPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'all' => ['required', 'boolean'],
            'off' => ['present', 'array', 'max:50'],
            'off.*' => ['string', 'exists:provider_categories,key'],
        ];
    }

    /** @return list<string> */
    public function off(): array
    {
        return array_values(array_filter((array) $this->input('off', []), 'is_string'));
    }
}
