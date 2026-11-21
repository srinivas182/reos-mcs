<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Onboarding;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class WaitlistRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|string>> */
    public function rules(): array
    {
        return [
            /** The app's device id, so the same phone joins once before an account exists. */
            'device_id' => ['required_without:email', 'nullable', 'uuid'],
            /** To be told the day the state opens (optional on the phone). */
            'email' => ['nullable', 'email:rfc', 'max:255'],
        ];
    }
}
