<?php

declare(strict_types=1);

namespace App\Http\Requests\Brokerage;

use App\Brokerage\BrokerageStaff;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The duties an office admin or team leader has (A15.2). */
final class DutiesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'duties' => ['present', 'array', 'max:4'],
            'duties.*' => ['string', Rule::in(BrokerageStaff::DUTIES)],
        ];
    }

    /** @return list<string> */
    public function duties(): array
    {
        $duties = $this->input('duties');

        return array_values(array_filter(is_array($duties) ? $duties : [], is_string(...)));
    }
}
