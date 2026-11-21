<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalogue;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** "My tool isn't listed". */
final class ToolRequestRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|string>> */
    public function rules(): array
    {
        return [
            /** The tool's name, as the person knows it. */
            'name' => ['required', 'string', 'min:2', 'max:120'],
            /** What they use it for (optional). */
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
