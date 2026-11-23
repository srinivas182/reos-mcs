<?php

declare(strict_types=1);

namespace App\Http\Requests\Agents;

use App\Agents\AgentModes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

/** An agent's goal (mode), and how it was chosen (A11.1a). */
final class GoalRequest extends FormRequest
{
    /** @return array<string, list<string|In>> */
    public function rules(): array
    {
        return [
            'goal' => ['required', 'string', Rule::in(AgentModes::GOALS)],
            'reason' => ['nullable', 'string', Rule::in(['setup', 'manual', 'suggested'])],
        ];
    }

    /** @return 'setup'|'manual'|'suggested' */
    public function reason(): string
    {
        return match ($this->string('reason')->toString()) {
            'setup' => 'setup',
            'suggested' => 'suggested',
            default => 'manual',
        };
    }
}
