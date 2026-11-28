<?php

declare(strict_types=1);

namespace App\Http\Requests\Providers;

use Illuminate\Foundation\Http\FormRequest;

/** Providers near you (B16.3): optionally one category. */
final class NearYouRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['category' => ['nullable', 'string', 'exists:provider_categories,key']];
    }

    public function category(): ?string
    {
        return $this->filled('category') ? $this->string('category')->toString() : null;
    }
}
