<?php

declare(strict_types=1);

namespace App\Http\Requests\Brokerage;

use App\Tenancy\TenantLimits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Who pays for agents' seats, and whether AI credits are pooled (A15.1b). */
final class SeatsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'payer' => ['required', Rule::in(TenantLimits::PAYERS)],
            'credits_pooled' => ['required', 'boolean'],
        ];
    }
}
