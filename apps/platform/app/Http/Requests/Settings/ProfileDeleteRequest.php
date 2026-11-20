<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileDeleteRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();

        // People who signed up with a phone, Google or Apple have no password;
        // they confirm by ticking "I understand" instead.
        return $user !== null && $user->password === null
            ? ['confirm' => ['accepted']]
            : ['password' => $this->currentPasswordRules()];
    }
}
