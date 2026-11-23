<?php

declare(strict_types=1);

namespace App\Http\Requests\Crm;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** A contact the agent adds in REOS (B8.3b): a name, an email or a phone at least. */
final class AddContactRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|string>> */
    public function rules(): array
    {
        return [
            'first_name' => ['nullable', 'string', 'max:100', 'required_without_all:last_name,email,phone'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email:rfc', 'max:191'],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+()\-.\s]{7,40}$/'],
            'stage' => ['nullable', 'string', 'max:60'],
        ];
    }

    /** @return array{first_name: string|null, last_name: string|null, emails: list<string>, phones: list<string>, stage: string|null} */
    public function person(): array
    {
        $text = fn (string $key): ?string => $this->filled($key) ? trim($this->string($key)->toString()) : null;
        $email = $text('email');
        $phone = $text('phone');

        return ['first_name' => $text('first_name'), 'last_name' => $text('last_name'), 'emails' => $email === null ? [] : [$email], 'phones' => $phone === null ? [] : [$phone], 'stage' => $text('stage')];
    }
}
