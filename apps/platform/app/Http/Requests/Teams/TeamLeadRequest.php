<?php

declare(strict_types=1);

namespace App\Http\Requests\Teams;

use App\Teams\LeadRouting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A lead for the team (A17.3), added by the team leader or from a source. */
final class TeamLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['source' => ['required', Rule::in(LeadRouting::SOURCES)], 'name' => ['required', 'string', 'max:160'], 'email' => ['nullable', 'email', 'max:200'], 'phone' => ['nullable', 'string', 'max:40'], 'language' => ['nullable', Rule::in(['en', 'es'])], 'message' => ['nullable', 'string', 'max:1000']];
    }
}
