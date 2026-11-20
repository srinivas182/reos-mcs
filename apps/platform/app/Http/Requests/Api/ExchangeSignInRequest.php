<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;

final class ExchangeSignInRequest extends DeviceRequest
{
    /** @return array<string, array<int, ValidationRule|string>> */
    public function rules(): array
    {
        return [
            /** The one-time code the browser returned to the app with. */
            'code' => ['required', 'string', 'size:48'],
            /** The PKCE verifier the app created before opening the browser. */
            'code_verifier' => ['required', 'string', 'min:43', 'max:128'],
            ...$this->deviceRules(),
        ];
    }
}
