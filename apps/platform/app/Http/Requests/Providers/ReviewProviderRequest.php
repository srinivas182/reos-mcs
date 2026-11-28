<?php

declare(strict_types=1);

namespace App\Http\Requests\Providers;

use Illuminate\Foundation\Http\FormRequest;

/** A review of a provider (B16.3): 1 to 5 stars and an optional note. */
final class ReviewProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            'body' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function body(): ?string
    {
        return $this->filled('body') ? $this->string('body')->toString() : null;
    }
}
