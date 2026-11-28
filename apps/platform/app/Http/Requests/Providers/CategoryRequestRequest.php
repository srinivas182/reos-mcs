<?php

declare(strict_types=1);

namespace App\Http\Requests\Providers;

use Illuminate\Foundation\Http\FormRequest;

/** Ask REOS to add a category that isn't listed (B15.1). */
final class CategoryRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            /** What the provider does, such as "Home stager". */
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'note' => ['nullable', 'string', 'max:300'],
        ];
    }
}
