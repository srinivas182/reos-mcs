<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Content;

use App\Content\CourseType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\In;

/**
 * A new course, or changes to one (A5.3): title, type, states, hours,
 * description and, for CE and qualifying courses, the approval number per
 * state. On a change, only the fields sent are updated.
 */
final class CourseRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_array($this->input('states'))) {
            $this->merge(['states' => array_values(array_map(static fn (mixed $code): mixed => is_string($code) ? strtoupper($code) : $code, $this->input('states')))]);
        }
    }

    /** @return array<string, array<int, ValidationRule|string|In|Exists>> */
    public function rules(): array
    {
        $creating = $this->isMethod('POST');
        $required = $creating ? 'required' : 'sometimes';

        return [
            'title' => [$required, 'string', 'min:3', 'max:200'],
            'type' => [$required, Rule::in(CourseType::values())],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'states' => ['sometimes', 'nullable', 'array', 'max:60'],
            'states.*' => ['string', 'size:2', Rule::exists('states', 'code')],
            'hours' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:500'],
            'approvals' => ['sometimes', 'nullable', 'array'],
            'approvals.*' => ['array'],
            'approvals.*.number' => ['nullable', 'string', 'max:60'],
            'approvals.*.expires_on' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
