<?php

declare(strict_types=1);

namespace App\Http\Requests\Organisation;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** Whether the brokerage's agents may use a tool (screen 40). */
final class AllowIntegrationRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|string>> */
    public function rules(): array
    {
        return ['allowed' => ['required', 'boolean']];
    }

    public function allowed(): bool
    {
        return $this->boolean('allowed');
    }
}
