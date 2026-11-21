<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Authorization\Role;
use App\Catalogue\CatalogueStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\Rules\RequiredIf;

/** Availability rules for one tool (web journey screen 65). */
final class CatalogueSettingsRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|Enum|Exists|In|RequiredIf|string>> */
    public function rules(): array
    {
        return [
            // Null goes back to the register's status.
            'status' => ['nullable', Rule::enum(CatalogueStatus::class)],
            'retires_on' => [Rule::requiredIf(fn (): bool => $this->input('status') === CatalogueStatus::Retired->value), 'nullable', 'date_format:Y-m-d'],
            'states' => ['nullable', 'array'],
            'states.*' => ['string', 'size:2', Rule::exists('states', 'code')],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', Rule::in(array_map(static fn (Role $r): string => $r->value, Role::cases()))],
            'beta_organisations' => ['array'],
            'beta_organisations.*' => ['integer', Rule::exists('organisations', 'id')],
            'tenants_may_hide' => ['required', 'boolean'],
            'tenants_may_require' => ['required', 'boolean'],
            'show_coming_soon' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array{status: CatalogueStatus|null, retires_on: string|null, states: list<string>|null, roles: list<string>|null, beta_organisations: list<int>, tenants_may_hide: bool, tenants_may_require: bool, show_coming_soon: bool}
     */
    public function changes(): array
    {
        // Validated above: lists of strings (states, roles) and organisation ids.
        $list = fn (string $key): ?array => $this->input($key) === null
            ? null
            : array_values(array_unique(array_filter((array) $this->input($key), 'is_string')));

        return [
            'status' => $this->filled('status') ? CatalogueStatus::from($this->string('status')->toString()) : null,
            'retires_on' => $this->filled('retires_on') ? Carbon::createFromFormat('Y-m-d', $this->string('retires_on')->toString())?->toDateString() : null,
            'states' => $list('states'),
            'roles' => $list('roles'),
            'beta_organisations' => array_values(array_unique(array_map(
                static fn (mixed $id): int => is_numeric($id) ? intval($id) : 0,
                (array) $this->input('beta_organisations', []),
            ))),
            'tenants_may_hide' => $this->boolean('tenants_may_hide'),
            'tenants_may_require' => $this->boolean('tenants_may_require'),
            'show_coming_soon' => $this->boolean('show_coming_soon'),
        ];
    }
}
