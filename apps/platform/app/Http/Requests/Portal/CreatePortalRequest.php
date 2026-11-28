<?php

declare(strict_types=1);

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

/** A client to share the deal with (B14.2). */
final class CreatePortalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['nullable', 'string', 'email', 'max:160'],
            'language' => ['nullable', 'string', 'in:en,es'],
        ];
    }
}
