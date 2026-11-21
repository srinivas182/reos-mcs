<?php

declare(strict_types=1);

namespace App\Http\Requests\Onboarding;

use App\Models\State;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/** "Where do you want to be licensed?" on reos.com. */
final class ChooseStateRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|Exists|string>> */
    public function rules(): array
    {
        return [
            'state' => ['required', 'string', 'size:2', Rule::exists('states', 'code')],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('state'))) {
            $this->merge(['state' => strtoupper($this->input('state'))]);
        }
    }

    public function state(): State
    {
        return State::query()->findOrFail($this->string('state')->toString());
    }
}
