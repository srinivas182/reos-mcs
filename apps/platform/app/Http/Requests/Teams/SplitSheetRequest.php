<?php

declare(strict_types=1);

namespace App\Http\Requests\Teams;

use App\Teams\LeadRouting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A split sheet for a closing (A17.3). */
final class SplitSheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['agent_id' => ['required', 'integer'], 'property' => ['required', 'string', 'max:200'], 'closing_on' => ['required', 'date'], 'gross_cents' => ['required', 'integer', 'min:1', 'max:100000000'], 'lead_source' => ['nullable', Rule::in(LeadRouting::SOURCES)]];
    }
}
