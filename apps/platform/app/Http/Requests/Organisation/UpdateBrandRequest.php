<?php

declare(strict_types=1);

namespace App\Http\Requests\Organisation;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** Save on Branding and domain (screen 57). The colour's contrast is checked by the Brands service. */
final class UpdateBrandRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|string>> */
    public function rules(): array
    {
        return [
            'primary' => ['sometimes', 'nullable', 'string', 'regex:/^#?[0-9A-Fa-f]{6}$/'],
            'app_label' => ['sometimes', 'nullable', 'string', 'max:60'],
            'email_from_name' => ['sometimes', 'nullable', 'string', 'max:80'],
            'reply_to' => ['sometimes', 'nullable', 'email:rfc', 'max:254'],
        ];
    }

    /** @return array{primary?: string|null, app_label?: string|null, email_from_name?: string|null, reply_to?: string|null} */
    public function changes(): array
    {
        $changes = [];

        foreach (['primary', 'app_label', 'email_from_name', 'reply_to'] as $key) {
            if ($this->exists($key)) {
                $changes[$key] = $this->filled($key) ? $this->string($key)->trim()->toString() : null;
            }
        }

        return $changes;
    }
}
