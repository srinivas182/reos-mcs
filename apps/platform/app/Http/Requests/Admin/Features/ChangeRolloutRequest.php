<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Features;

use App\Enums\OrganisationType;
use App\Features\Flags;
use App\Features\RolloutStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Change rollout (screen 70): the step, the named tenants and states, the percentage, roles and threshold. */
final class ChangeRolloutRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'stage' => ['required', Rule::enum(RolloutStage::class)],
            'tenant_ids' => ['present', 'array', 'max:200'],
            'tenant_ids.*' => ['integer', 'distinct', Rule::exists('organisations', 'id')->whereNot('type', OrganisationType::Platform->value)],
            'states' => ['present', 'array', 'max:60'],
            'states.*' => ['string', 'distinct', Rule::exists('states', 'code')],
            'percent' => ['required', 'integer', 'min:0', 'max:100'],
            'roles' => ['present', 'array'],
            'roles.*' => ['string', 'distinct', Rule::in(Flags::roles())],
            'error_threshold' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array{stage: RolloutStage, tenant_ids: list<int>, states: list<string>, percent: int, roles: list<string>|null, error_threshold: int}
     */
    public function rollout(): array
    {
        $ints = static fn (mixed $values): array => is_array($values) ? array_values(array_map(intval(...), array_filter($values, is_numeric(...)))) : [];
        $strings = static fn (mixed $values): array => is_array($values) ? array_values(array_filter($values, is_string(...))) : [];
        $roles = $strings($this->input('roles'));

        return [
            'stage' => $this->enum('stage', RolloutStage::class) ?? RolloutStage::Off,
            'tenant_ids' => $ints($this->input('tenant_ids')),
            'states' => $strings($this->input('states')),
            'percent' => $this->integer('percent'),
            'roles' => $roles === [] ? null : $roles,
            'error_threshold' => $this->integer('error_threshold'),
        ];
    }
}
