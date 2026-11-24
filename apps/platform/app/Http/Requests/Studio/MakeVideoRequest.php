<?php

declare(strict_types=1);

namespace App\Http\Requests\Studio;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Make a video (B12.2; screens 165 and 166): a listing, or my own clips and photos. */
final class MakeVideoRequest extends FormRequest
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
            'source.type' => ['required', Rule::in(['listing', 'own'])],
            'source.id' => ['nullable', 'required_if:source.type,listing', 'uuid'],
            'source.text' => ['nullable', 'string', 'max:300'],
            'clips' => ['nullable', 'array', 'max:6'],
            'clips.*' => ['required', 'string', 'max:255'],
        ];
    }
}
