<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Tenants;

use App\Enums\OrganisationType;
use App\Tenancy\TenantPlan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\RequiredIf;

/** New tenant (screen 59). */
final class StoreTenantRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|Enum|Exists|RequiredIf|string>> */
    public function rules(): array
    {
        $types = array_values(array_filter(OrganisationType::cases(), static fn (OrganisationType $t): bool => $t !== OrganisationType::Platform));

        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'type' => ['required', Rule::enum(OrganisationType::class)->only($types)],
            // A team sits inside a brokerage.
            'parent_id' => [Rule::requiredIf(fn (): bool => $this->input('type') === OrganisationType::Team->value), 'nullable', 'integer',
                Rule::exists('organisations', 'id')->where('type', OrganisationType::Brokerage->value)],
            'states' => ['required', 'array', 'min:1'],
            'states.*' => ['string', 'size:2', Rule::exists('states', 'code')],
            'plan' => ['required', Rule::enum(TenantPlan::class)],
            'seats' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'trial_days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ];
    }

    /** @return array{type: OrganisationType, name: string, parent_id: int|null, states: list<string>, plan: string, seats: int|null, trial_days: int|null} */
    public function tenant(): array
    {
        /** @var list<string> $states */
        $states = array_values(array_map(static fn (mixed $s): string => is_string($s) ? strtoupper($s) : '', (array) $this->input('states')));

        return [
            'type' => OrganisationType::from($this->string('type')->toString()),
            'name' => $this->string('name')->trim()->toString(),
            'parent_id' => $this->filled('parent_id') ? $this->integer('parent_id') : null,
            'states' => $states,
            'plan' => $this->string('plan')->toString(),
            'seats' => $this->filled('seats') ? $this->integer('seats') : null,
            'trial_days' => $this->filled('trial_days') ? $this->integer('trial_days') : null,
        ];
    }
}
