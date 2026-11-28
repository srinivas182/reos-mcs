<?php

declare(strict_types=1);

namespace App\Http\Requests\Teams;

use App\Teams\LeadRouting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Lead routing rules (A17.3): sources, method, claim time, fallback and off hours. */
final class RoutingRulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['sources' => ['present', 'array'], 'sources.*' => ['string', Rule::in(LeadRouting::SOURCES)], 'method' => ['required', Rule::in(['first_to_claim', 'round_robin'])], 'claim_minutes' => ['required', 'integer', 'between:1,30'], 'fallback' => ['required', Rule::in(['round_robin', 'leader'])], 'off_start' => ['required', 'date_format:H:i'], 'off_end' => ['required', 'date_format:H:i']];
    }
}
