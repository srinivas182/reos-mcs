<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Billing;

use App\Billing\Buyer;
use App\Billing\ProductKind;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

/**
 * A new plan, or changes to one (screen 67). Features are entitlement keys
 * such as `learn.qualifying` or `agent.core`; includes are plain words.
 */
final class ProductRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['includes', 'features'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $this->merge([$field => array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $value) ?: [])))]);
            }
        }
    }

    /** @return array<string, array<int, ValidationRule|In|string>> */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'min:3', 'max:160'],
            'kind' => [$required, Rule::in(ProductKind::values())],
            'buyer' => [$required, Rule::in(Buyer::values())],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'includes' => ['sometimes', 'nullable', 'array', 'max:12'],
            'includes.*' => ['string', 'max:160'],
            'features' => ['sometimes', 'nullable', 'array', 'max:20'],
            'features.*' => ['string', 'max:80', 'regex:/^[a-z][a-z0-9_]*(\.[a-z0-9_]+)+$/'],
            'ai_help' => ['sometimes', 'nullable', 'string', 'max:160'],
            'credits' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000000'],
            'seats' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000'],
        ];
    }
}
