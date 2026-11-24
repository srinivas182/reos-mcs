<?php

declare(strict_types=1);

namespace App\Http\Requests\School;

use App\Models\CeReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A CE report for a date range: TREC's file, or REOS submits with the school's authority (A11.3). */
final class CeReportRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'from_on' => ['required', 'date_format:Y-m-d'],
            'to_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:from_on', 'before_or_equal:today'],
            'method' => ['required', Rule::in(CeReport::METHODS)],
            // The school authorises REOS to submit on its behalf.
            'authorised' => ['required_if:method,reos', 'accepted_if:method,reos'],
        ];
    }
}
