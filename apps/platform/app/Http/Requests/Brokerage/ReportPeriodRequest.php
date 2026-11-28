<?php

declare(strict_types=1);

namespace App\Http\Requests\Brokerage;

use App\Brokerage\Reports;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The period a brokerage report covers (A16.3): this month, last month, this quarter, this year or last year. */
final class ReportPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['period' => ['nullable', 'string', Rule::in(Reports::PERIODS)]];
    }

    /** @return 'this_month'|'last_month'|'this_quarter'|'this_year'|'last_year' */
    public function period(): string
    {
        return match ($this->string('period')->toString()) {
            'this_month' => 'this_month',
            'last_month' => 'last_month',
            'this_quarter' => 'this_quarter',
            'last_year' => 'last_year',
            default => 'this_year',
        };
    }
}
