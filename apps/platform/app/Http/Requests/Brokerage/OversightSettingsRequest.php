<?php

declare(strict_types=1);

namespace App\Http\Requests\Brokerage;

use Illuminate\Foundation\Http\FormRequest;

/** The brokerage's "needs help" thresholds (A16.1). */
final class OversightSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            /** Days without a sign-in, conversation or appointment. */
            'quiet_days' => ['required', 'integer', 'min:3', 'max:60'],
            /** Days without a new, open or closed deal. */
            'no_deal_days' => ['required', 'integer', 'min:30', 'max:365'],
            /** How long an agent counts as new. */
            'new_days' => ['required', 'integer', 'min:30', 'max:365'],
        ];
    }

    /** @return array{quiet_days: int, no_deal_days: int, new_days: int} */
    public function settings(): array
    {
        return ['quiet_days' => $this->integer('quiet_days'), 'no_deal_days' => $this->integer('no_deal_days'), 'new_days' => $this->integer('new_days')];
    }
}
