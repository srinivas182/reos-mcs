<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A change to one state rule, saved as a draft (screen 62). The value is
 * checked against the rule's type in `StateRules::draft`.
 */
final class StateRuleDraftRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|string>> */
    public function rules(): array
    {
        return [
            'value' => ['required', 'array'],
            'citation' => ['required', 'string', 'max:200'],
            'source_url' => ['nullable', 'url:https', 'max:500'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
