<?php

declare(strict_types=1);

namespace App\Http\Requests\RolePlay;

use Illuminate\Foundation\Http\FormRequest;

/** What the agent said in a role-play (A14.1a): spoken words or typing. */
final class SayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['text' => ['required', 'string', 'min:1', 'max:1500']];
    }
}
