<?php

declare(strict_types=1);

namespace App\Http\Requests\VirtualBroker;

use Illuminate\Foundation\Http\FormRequest;

/** Who answers escalations and how fast (A12.1; broker journey screen 5). */
final class VirtualBrokerSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'answerer_id' => ['nullable', 'integer', 'min:1'],
            'reply_hours' => ['required', 'integer', 'min:1', 'max:72'],
        ];
    }
}
