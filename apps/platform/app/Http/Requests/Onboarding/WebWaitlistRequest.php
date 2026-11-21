<?php

declare(strict_types=1);

namespace App\Http\Requests\Onboarding;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** Join a state's waitlist from the website: an email to tell the day it opens. */
final class WebWaitlistRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|string>> */
    public function rules(): array
    {
        return [
            'email' => [$this->user() === null ? 'required' : 'nullable', 'email:rfc', 'max:255'],
        ];
    }
}
