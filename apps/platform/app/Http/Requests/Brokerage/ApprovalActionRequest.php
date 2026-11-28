<?php

declare(strict_types=1);

namespace App\Http\Requests\Brokerage;

use Illuminate\Foundation\Http\FormRequest;

/** Clear one item in the approvals queue (A15.3): approve, return with a note, or answer. */
final class ApprovalActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', 'in:approve,return,answer'],
            'text' => ['nullable', 'string', 'max:4000'],
            'add_to_knowledge' => ['sometimes', 'boolean'],
        ];
    }

    /** @return 'approve'|'return'|'answer' */
    public function action(): string
    {
        return match ($this->string('action')->toString()) {
            'return' => 'return',
            'answer' => 'answer',
            default => 'approve',
        };
    }
}
