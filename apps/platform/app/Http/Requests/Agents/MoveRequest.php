<?php

declare(strict_types=1);

namespace App\Http\Requests\Agents;

use Illuminate\Foundation\Http\FormRequest;

/** An invite code to join another brokerage or team (A14.3), and the agent's confirmation. */
final class MoveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20'],
            'confirm' => ['sometimes', 'boolean'],
        ];
    }
}
