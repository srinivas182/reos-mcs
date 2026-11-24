<?php

declare(strict_types=1);

namespace App\Http\Requests\Studio;

use App\Studio\StudioGenerator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Make content (B12.1; screen 165): where it starts from, and what to make. */
final class MakeContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'source' => ['required', 'array'],
            'source.type' => ['required', Rule::in(['listing', 'sale', 'market', 'text'])],
            'source.id' => ['nullable', 'uuid'],
            'source.text' => ['nullable', 'string', 'max:1000'],
            'source.city' => ['nullable', 'string', 'max:80'],
            'outputs' => ['required', 'array', 'min:1'],
            'outputs.*' => ['required', Rule::in(StudioGenerator::OUTPUTS)],
        ];
    }
}
