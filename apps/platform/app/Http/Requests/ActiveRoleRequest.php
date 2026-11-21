<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Authorization\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class ActiveRoleRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|Enum|string>> */
    public function rules(): array
    {
        return [
            /** One of the roles the person holds (see `roles` in `GET /me`). */
            'role' => ['required', 'string', Rule::enum(Role::class)],
            /** The organisation that role is held in. */
            'organisation_id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function role(): Role
    {
        return Role::from($this->string('role')->toString());
    }

    public function organisationId(): int
    {
        return $this->integer('organisation_id');
    }
}
