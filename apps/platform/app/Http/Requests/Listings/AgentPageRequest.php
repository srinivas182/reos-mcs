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
            // The card (B14.1): only what the agent chooses to show.
            'phone' => ['sometimes', 'nullable', 'string', 'regex:/^\+?[0-9 ().-]{7,30}$/'],
            'email' => ['sometimes', 'nullable', 'string', 'email', 'max:120'],
            'areas' => ['sometimes', 'nullable', 'array', 'max:6'],
            'areas.*' => ['string', 'min:2', 'max:60'],
        ];
    }

    /**
     * The card's contact details and areas, when sent.
     *
     * @return array{phone?: string|null, email?: string|null, areas?: list<string>}
     */
    public function card(): array
    {
        $card = [];

        foreach (['phone', 'email'] as $key) {
            if ($this->exists($key)) {
                $card[$key] = $this->filled($key) ? trim($this->string($key)->toString()) : null;
            }
        }

        if ($this->exists('areas')) {
            $areas = $this->input('areas');
            $card['areas'] = is_array($areas) ? array_values(array_unique(array_filter(array_map(static fn (mixed $a): string => is_string($a) ? trim($a) : '', $areas)))) : [];
        }

        return $card;
    }

    public function bio(): ?string
    {
        return $this->filled('bio') ? $this->string('bio')->toString() : null;
    }
}
