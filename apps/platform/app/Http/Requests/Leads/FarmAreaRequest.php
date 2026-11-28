<?php

declare(strict_types=1);

namespace App\Http\Requests\Leads;

use Illuminate\Foundation\Http\FormRequest;

/** An area to farm (B14.3c): a city or a ZIP code, the contacts' tag, and the sender's postal address. */
final class FarmAreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'city' => ['nullable', 'required_without:postal_code', 'string', 'max:80'],
            'postal_code' => ['nullable', 'required_without:city', 'regex:/^\d{5}$/'],
            'tag' => ['required', 'string', 'min:1', 'max:60'],
            'mailing_address' => ['required', 'string', 'min:10', 'max:200'],
        ];
    }

    /** @return array{name: string, city: string|null, postal_code: string|null, tag: string, mailing_address: string} */
    public function area(): array
    {
        return [
            'name' => $this->string('name')->toString(),
            'city' => $this->filled('city') ? $this->string('city')->toString() : null,
            'postal_code' => $this->filled('postal_code') ? $this->string('postal_code')->toString() : null,
            'tag' => $this->string('tag')->toString(),
            'mailing_address' => $this->string('mailing_address')->toString(),
        ];
    }
}
