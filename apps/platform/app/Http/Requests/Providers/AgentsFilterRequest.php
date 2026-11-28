<?php

declare(strict_types=1);

namespace App\Http\Requests\Providers;

use App\ProviderNetwork\AgentsInArea;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Filters on Agents in my areas (B16.1): an activity level and one of the provider's areas. */
final class AgentsFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'level' => ['nullable', 'string', Rule::in(AgentsInArea::LEVELS)],
            'area' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return 'new'|'active'|'top'|null */
    public function level(): ?string
    {
        return match ($this->string('level')->toString()) {
            'new' => 'new',
            'active' => 'active',
            'top' => 'top',
            default => null,
        };
    }

    public function area(): ?int
    {
        return $this->filled('area') ? $this->integer('area') : null;
    }
}
