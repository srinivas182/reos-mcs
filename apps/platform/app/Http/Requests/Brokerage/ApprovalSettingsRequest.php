<?php

declare(strict_types=1);

namespace App\Http\Requests\Brokerage;

use Illuminate\Foundation\Http\FormRequest;

/** How many working hours an item may wait before the office admins are told (A15.3). */
final class ApprovalSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['fallback_hours' => ['required', 'integer', 'min:1', 'max:40']];
    }
}
