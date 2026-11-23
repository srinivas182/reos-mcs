<?php

declare(strict_types=1);

namespace App\Http\Requests\School;

use App\Content\Delivery;
use App\Models\CourseApproval;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\In;

/**
 * A course approval, or a change to one (A7.1, screen 50): state, delivery,
 * approval number, hours, approval and expiry dates, status, and the seat-time
 * and attendance rules. On a change, only the fields sent are updated.
 */
final class ApprovalRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('state_code'))) {
            $this->merge(['state_code' => strtoupper($this->input('state_code'))]);
        }
    }

    /** @return array<string, array<int, ValidationRule|string|In|Exists>> */
    public function rules(): array
    {
        $creating = $this->isMethod('POST');
        $required = $creating ? 'required' : 'prohibited';

        return [
            'course_id' => [$required, 'integer'],
            'state_code' => [$required, 'string', 'size:2', Rule::exists('states', 'code')],
            'delivery' => [$creating ? 'required' : 'prohibited', Rule::in(Delivery::values())],
            'number' => ['sometimes', 'nullable', 'string', 'max:60'],
            'hours' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:500'],
            'approved_on' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'expires_on' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'status' => ['sometimes', Rule::in([CourseApproval::PENDING, CourseApproval::APPROVED, CourseApproval::WITHDRAWN])],
            'max_hours_per_day' => ['sometimes', 'nullable', 'numeric', 'min:1', 'max:24'],
            'checkin_minutes' => ['sometimes', 'nullable', 'integer', 'min:5', 'max:120'],
            'presence_percent' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'makeup_min_percent' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'makeup_days' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:365'],
            'note' => ['sometimes', 'nullable', 'string', 'max:500'],
            // Live classes of the course may be recorded for the course (A10.2c).
            'records_live' => ['sometimes', 'boolean'],
        ];
    }
}
