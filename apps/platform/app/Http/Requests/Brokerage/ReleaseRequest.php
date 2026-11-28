<?php

declare(strict_types=1);

namespace App\Http\Requests\Brokerage;

use Illuminate\Foundation\Http\FormRequest;

/** Release an agent's sponsorship (A16.2): the date, and who takes their open deals. */
final class ReleaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'agent_id' => ['required', 'integer'],
            'effective_on' => ['required', 'date'],
            /** The agent or broker who takes the open deals; needed when there are any. */
            'handover_to' => ['nullable', 'integer'],
        ];
    }
}
