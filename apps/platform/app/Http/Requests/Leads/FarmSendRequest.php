<?php

declare(strict_types=1);

namespace App\Http\Requests\Leads;

use Illuminate\Foundation\Http\FormRequest;

/** The market update as the agent approved it (B14.3c). */
final class FarmSendRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'min:3', 'max:160'],
            'body' => ['required', 'string', 'min:20', 'max:5000'],
        ];
    }
}
