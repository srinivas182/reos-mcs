<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;

final class IdentitySignInRequest extends DeviceRequest
{
    /** @return array<string, array<int, ValidationRule|string>> */
    public function rules(): array
    {
        return [
            /** The ID token the Google or Apple SDK returned on the phone. */
            'id_token' => ['required', 'string', 'max:8192'],
            /** Apple shares the person's name only on their first sign-in; the app passes it on. */
            'name' => ['nullable', 'string', 'max:255'],
            ...$this->deviceRules(),
        ];
    }

    public function personName(): ?string
    {
        $name = $this->string('name')->squish()->toString();

        return $name === '' ? null : $name;
    }
}
