<?php

declare(strict_types=1);

namespace App\Http\Requests\Agents;

use App\Agents\AgentBriefs;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** When the morning brief goes out and what it covers (A11.1c). */
final class BriefSettingsRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'at' => ['required', 'string', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'timezone' => ['required', 'string', 'timezone:all'],
            'sections' => ['present', 'array', 'max:'.count(AgentBriefs::SECTIONS)],
            'sections.*' => ['string', 'distinct', Rule::in(AgentBriefs::SECTIONS)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['at.regex' => self::line('agents.brief.errors.time'), 'timezone.timezone' => self::line('agents.brief.errors.timezone')];
    }

    /** @return list<string> */
    public function sections(): array
    {
        $sections = $this->input('sections');

        return is_array($sections) ? array_values(array_filter($sections, 'is_string')) : [];
    }

    private static function line(string $key): string
    {
        $line = __($key);

        return is_string($line) ? $line : $key;
    }
}
