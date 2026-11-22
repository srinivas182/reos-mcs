<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

/** Turn a privacy switch on or off, or withdraw a consent given for one thing (A6.3). */
final class ConsentRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'granted' => ['required', 'boolean'],
            'subject' => ['nullable', 'string', 'max:80', 'regex:/^[a-z_]+:[0-9]+$/'],
        ];
    }

    public function subject(): ?string
    {
        $subject = $this->input('subject');

        return is_string($subject) && $subject !== '' ? $subject : null;
    }
}
