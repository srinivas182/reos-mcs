<?php

declare(strict_types=1);

namespace App\Http\Requests\Communities;

use Illuminate\Foundation\Http\FormRequest;

/** The community's name and about (B17.2). */
final class CommunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'min:3', 'max:120'], 'about' => ['nullable', 'string', 'max:2000']];
    }
}
