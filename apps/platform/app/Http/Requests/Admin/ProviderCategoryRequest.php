<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\ProviderCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\In;

/**
 * A provider category and its flat monthly prices (screen 68). RESPA: a
 * price is a flat monthly fee for listing and advertising; nothing here can
 * be per lead, referral or closed deal.
 */
final class ProviderCategoryRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $fields = $this->input('profile_fields');

        if (is_string($fields)) {
            $this->merge(['profile_fields' => array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $fields) ?: [])))]);
        }
    }

    /** @return array<string, array<int, ValidationRule|Exists|In|string>> */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'min:3', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            /** What REOS checks before a provider goes live, such as "NMLS" or "TREC inspector". */
            'licence_check' => ['sometimes', 'nullable', 'string', 'max:120'],
            'verification' => ['sometimes', 'nullable', 'string', 'max:200'],
            'profile_fields' => ['sometimes', 'nullable', 'array', 'max:20'],
            'profile_fields.*' => ['string', 'max:80'],
            'special_permissions' => ['sometimes', 'nullable', 'string', 'max:200'],
            'areas_on_pro' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'status' => ['sometimes', Rule::in(ProviderCategory::STATUSES)],
            /** Flat monthly prices, in dollars: one row per state and tier. */
            'prices' => ['sometimes', 'array', 'max:150'],
            'prices.*.state' => ['required', 'string', 'size:2', Rule::exists('states', 'code')],
            'prices.*.tier' => ['required', Rule::in(ProviderCategory::TIERS)],
            'prices.*.monthly' => ['required', 'numeric', 'min:0', 'max:10000'],
        ];
    }

    /** @return array<string, array<string, int>> state => tier => cents */
    public function prices(): array
    {
        $rows = $this->validated('prices');
        $prices = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            if (is_array($row) && is_string($row['state'] ?? null) && is_string($row['tier'] ?? null) && is_numeric($row['monthly'] ?? null)) {
                $prices[strtoupper($row['state'])][$row['tier']] = (int) round((float) $row['monthly'] * 100);
            }
        }

        return $prices;
    }
}
