<?php

declare(strict_types=1);

namespace App\Http\Requests\RolePlay;

use App\RolePlay\Scenarios;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Starting a role-play (A14.1a): a type and one of its personas. */
final class StartRolePlayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(Scenarios::TYPES)],
            'persona' => ['required', 'string', Rule::in(array_merge(...array_values(Scenarios::PERSONAS)))],
        ];
    }
}
