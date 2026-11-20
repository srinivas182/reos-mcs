<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;

final class PhoneVerifyRequest extends DeviceRequest
{
    /** @return array<string, array<int, ValidationRule|string>> */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:/^\+[1-9][0-9]{7,14}$/'],
            /** The six-digit code from the text message. */
            'code' => ['required', 'string', 'digits:6'],
            ...$this->deviceRules(),
        ];
    }

    public function phone(): string
    {
        return $this->string('phone')->toString();
    }
}
