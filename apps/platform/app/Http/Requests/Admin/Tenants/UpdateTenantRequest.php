<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Tenants;

use App\Tenancy\TenantFeature;
use App\Tenancy\TenantLimits;
use App\Tenancy\TenantPlan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\In;

/** Save on a tenant (screen 60): plan, states, trial, features, limits and retention. */
final class UpdateTenantRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|Enum|Exists|In|string>> */
    public function rules(): array
    {
        $switchable = array_map(static fn (TenantFeature $f): string => $f->value, array_filter(TenantFeature::cases(), static fn (TenantFeature $f): bool => $f->switchable()));

        $rules = [
            'plan' => ['sometimes', Rule::enum(TenantPlan::class)],
            'states' => ['sometimes', 'array', 'min:1'],
            'states.*' => ['string', 'size:2', Rule::exists('states', 'code')],
            'trial_ends_at' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after:today'],
            'features' => ['sometimes', 'array:'.implode(',', $switchable)],
            'limits' => ['sometimes', 'array:seats,payer,credits_per_seat,credits_pooled,candidate_pool,rate_limit,queue_weight,log_retention_years,deleted_user_days'],
            'limits.seats' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'limits.payer' => ['sometimes', Rule::in(TenantLimits::PAYERS)],
            'limits.credits_per_seat' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'limits.credits_pooled' => ['sometimes', 'boolean'],
            'limits.candidate_pool' => ['sometimes', 'integer', 'min:0', 'max:10000000'],
            'limits.rate_limit' => ['sometimes', 'integer', 'min:60', 'max:100000'],
            'limits.queue_weight' => ['sometimes', Rule::in(TenantLimits::QUEUE_WEIGHTS)],
            // Texas and federal rules keep transaction records for years; never under 4.
            'limits.log_retention_years' => ['sometimes', 'integer', 'min:4', 'max:10'],
            'limits.deleted_user_days' => ['sometimes', 'integer', 'min:7', 'max:90'],
        ];

        foreach ($switchable as $feature) {
            $rules["features.{$feature}"] = ['boolean'];
        }

        return $rules;
    }

    /** @return array{plan?: string, states?: list<string>, trial_ends_at?: string|null, features?: array<string, bool>, limits?: array<string, mixed>} */
    public function changes(): array
    {
        $changes = [];

        if ($this->has('plan')) {
            $changes['plan'] = $this->string('plan')->toString();
        }
        if ($this->has('states')) {
            $changes['states'] = array_values(array_map(static fn (mixed $s): string => is_string($s) ? strtoupper($s) : '', (array) $this->input('states')));
        }
        if ($this->exists('trial_ends_at')) {
            $changes['trial_ends_at'] = $this->filled('trial_ends_at') ? $this->string('trial_ends_at')->toString() : null;
        }
        if ($this->has('features')) {
            $changes['features'] = array_map(static fn (mixed $on): bool => (bool) $on, (array) $this->input('features'));
        }
        if ($this->has('limits')) {
            $limits = (array) $this->input('limits');
            foreach (['credits_pooled'] as $flag) {
                if (array_key_exists($flag, $limits)) {
                    $limits[$flag] = (bool) $limits[$flag];
                }
            }
            $changes['limits'] = $limits;
        }

        return $changes;
    }
}
