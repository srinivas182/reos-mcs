<?php

declare(strict_types=1);

namespace App\Http\Requests\VirtualBroker;

use Illuminate\Foundation\Http\FormRequest;

/** The broker's reply to an agent's question (A12.1; broker journey screen 12). */
final class BrokerReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'answer' => ['required', 'string', 'min:2', 'max:4000'],
            'add_to_knowledge' => ['required', 'boolean'],
        ];
    }
}
