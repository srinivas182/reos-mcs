<?php

declare(strict_types=1);

namespace App\Http\Requests\Practice;

use App\Practice\MathLab\MathLab;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

/** One math lab step to check, or to show (screen 48, A9.2c). */
final class MathCheckRequest extends FormRequest
{
    /** "$1,250.50" and "1250.5" are both 1250.5. */
    protected function prepareForValidation(): void
    {
        $value = $this->input('value');

        if (is_string($value)) {
            $this->merge(['value' => str_replace(['$', ',', ' ', '%'], '', $value)]);
        }
    }

    /** @return array<string, array<int, ValidationRule|string|In>> */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(app(MathLab::class)->kinds())],
            'seed' => ['required', 'integer', 'min:1', 'max:2000000000'],
            'step' => ['required', 'integer', 'min:1', 'max:10'],
            'value' => ['nullable', 'required_unless:reveal,true', 'numeric', 'between:-100000000,100000000'],
            'reveal' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['value.numeric' => __('practice.math.errors.number'), 'value.required_unless' => __('practice.math.errors.number')];
    }

    public function kind(): string
    {
        $kind = $this->validated('kind');

        return is_string($kind) ? $kind : '';
    }

    public function seed(): int
    {
        $seed = $this->validated('seed');

        return is_numeric($seed) ? (int) $seed : 0;
    }

    public function step(): int
    {
        $step = $this->validated('step');

        return is_numeric($step) ? (int) $step : 0;
    }

    public function value(): ?float
    {
        $value = $this->validated('value');

        return is_numeric($value) ? (float) $value : null;
    }

    public function reveal(): bool
    {
        return $this->boolean('reveal');
    }
}
