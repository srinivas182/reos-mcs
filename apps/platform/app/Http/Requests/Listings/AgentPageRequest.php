<?php

declare(strict_types=1);

namespace App\Http\Requests\Listings;

use Illuminate\Foundation\Http\FormRequest;

/** The agent's public page: on or off, and a short bio (B10.2b). */
final class AgentPageRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'published' => ['required', 'boolean'],
            'bio' => ['nullable', 'string', 'max:600'],
        ];
    }

    public function bio(): ?string
    {
        return $this->filled('bio') ? $this->string('bio')->toString() : null;
    }
}
