<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Billing;

use App\Billing\PriceInterval;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\In;

/** A new price for one state and interval, in dollars (screen 67). */
final class PriceRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('state'))) {
            $this->merge(['state' => strtoupper($this->input('state'))]);
        }
    }

    /** @return array<string, array<int, ValidationRule|In|Exists|string>> */
    public function rules(): array
    {
        return [
            'state' => ['required', 'string', 'size:2', Rule::exists('states', 'code')],
            'interval' => ['required', Rule::in(PriceInterval::values())],
            'amount' => ['required', 'numeric', 'min:0', 'max:100000'],
            'floor' => ['nullable', 'numeric', 'min:0', 'max:100000'],
        ];
    }

    public function cents(string $field): ?int
    {
        $value = $this->input($field);

        return is_numeric($value) ? (int) round((float) $value * 100) : null;
    }
}
