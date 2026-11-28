<?php

declare(strict_types=1);

namespace App\Http\Requests\Providers;

use Illuminate\Foundation\Http\FormRequest;

/** Answers to the category's profile fields, by field key (B15.1); each is checked against its type. */
final class ProfileFieldsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            /**
             * Field key => text, a list of strings, or true/false.
             *
             * @var array<string, string|bool|list<string>>
             */
            'fields' => ['required', 'array', 'max:20'],
        ];
    }

    /** @return array<array-key, mixed> */
    public function answers(): array
    {
        $fields = $this->validated('fields');

        return is_array($fields) ? $fields : [];
    }
}
