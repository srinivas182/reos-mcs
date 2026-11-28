<?php

declare(strict_types=1);

namespace App\Http\Requests\Communities;

use Illuminate\Foundation\Http\FormRequest;

/** Pin or unpin a post (B17.2). */
final class PinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['pinned' => ['required', 'boolean']];
    }
}
