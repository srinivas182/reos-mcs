<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Tenants;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** "Sign in as support (with consent)": the reason the owner reads (A4.1c). */
final class SupportAccessRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|string>> */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:10', 'max:500']];
    }

    public function reason(): string
    {
        return $this->string('reason')->trim()->toString();
    }
}
