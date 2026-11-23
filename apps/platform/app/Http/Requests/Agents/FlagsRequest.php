<?php

declare(strict_types=1);

namespace App\Http\Requests\Agents;

use App\Agents\AgentModes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

/** An agent's work-style flags (A11.1a). */
final class FlagsRequest extends FormRequest
{
    /** @return array<string, list<string|In>> */
    public function rules(): array
    {
        return ['flags' => ['present', 'array'], 'flags.*' => ['string', Rule::in(AgentModes::FLAGS)]];
    }

    /** @return list<string> */
    public function flags(): array
    {
        $flags = $this->input('flags');

        return is_array($flags) ? array_values(array_filter($flags, is_string(...))) : [];
    }
}
