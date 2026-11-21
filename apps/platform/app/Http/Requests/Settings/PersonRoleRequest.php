<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Authorization\Role;
use App\Models\Organisation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Exists;

/** A role in an organisation, given to or taken from a person on the People screen. */
final class PersonRoleRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|Enum|Exists|string>> */
    public function rules(): array
    {
        return [
            'role' => ['required', 'string', Rule::enum(Role::class)],
            'organisation_id' => ['required', 'integer', Rule::exists('organisations', 'id')],
        ];
    }

    public function role(): Role
    {
        return Role::from($this->string('role')->toString());
    }

    public function organisation(): Organisation
    {
        return Organisation::query()->findOrFail($this->integer('organisation_id'));
    }
}
