<?php

declare(strict_types=1);

namespace App\Http\Requests\Licences;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** The state switcher (A5.1). */
final class ChooseStateRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|string>> */
    public function rules(): array
    {
        return [
            /** One of the person's live states, for example `TX`. */
            'state' => ['required', 'string', 'size:2'],
        ];
    }

    public function code(): string
    {
        return strtoupper($this->string('state')->toString());
    }
}
