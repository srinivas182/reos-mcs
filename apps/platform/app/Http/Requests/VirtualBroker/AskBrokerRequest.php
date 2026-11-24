<?php

declare(strict_types=1);

namespace App\Http\Requests\VirtualBroker;

use Illuminate\Foundation\Http\FormRequest;

/** A question to the virtual broker (A12.1): the words, and the deal it's about if any. */
final class AskBrokerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'min:3', 'max:1000'],
            'deal_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
