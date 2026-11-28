<?php

declare(strict_types=1);

namespace App\Http\Requests\Brokerage;

use Illuminate\Foundation\Http\FormRequest;

/** Invite a licensed agent to talk (A17.2): an optional first message. */
final class InviteAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['message' => ['nullable', 'string', 'max:1000']];
    }
}
