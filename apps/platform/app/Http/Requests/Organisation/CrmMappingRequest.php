<?php

declare(strict_types=1);

namespace App\Http\Requests\Organisation;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Where a brokerage CRM keeps a person's stage (screen 40). Field names are
 * API names; the service checks them against the fields the CRM has.
 */
final class CrmMappingRequest extends FormRequest
{
    private const string FIELD = 'regex:/^[A-Za-z][A-Za-z0-9_]*$/';

    /** @return array<string, array<int, ValidationRule|string>> */
    public function rules(): array
    {
        return [
            'contact_stage' => ['nullable', 'string', 'max:40', self::FIELD],
            'lead_stage' => ['required', 'string', 'max:40', self::FIELD],
            'leads' => ['required', 'boolean'],
        ];
    }

    /** @return array{contact_stage: string|null, lead_stage: string, leads: bool} */
    public function mapping(): array
    {
        $contact = $this->string('contact_stage')->toString();

        return ['contact_stage' => $contact === '' ? null : $contact, 'lead_stage' => $this->string('lead_stage')->toString(), 'leads' => $this->boolean('leads')];
    }
}
