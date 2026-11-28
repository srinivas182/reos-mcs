<?php

declare(strict_types=1);

namespace App\Http\Requests\Providers;

use Illuminate\Foundation\Http\FormRequest;

/** A service area: a city, a ZIP code, or a radius in miles around a ZIP code (B15.2). */
final class ServiceAreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'kind' => ['required', 'string', 'in:city,zip,radius'],
            'city' => ['nullable', 'string', 'max:80'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'radius_miles' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    /** @return 'city'|'zip'|'radius' */
    public function kind(): string
    {
        return match ($this->string('kind')->toString()) {
            'city' => 'city',
            'zip' => 'zip',
            default => 'radius',
        };
    }
}
