<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Content;

use App\Content\CourseStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

/** Moves a course to draft, in review, live or hidden (A5.3). */
final class CourseStatusRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|In|string>> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(CourseStatus::values())],
            'note' => ['nullable', 'string', 'max:300'],
        ];
    }

    public function status(): CourseStatus
    {
        return CourseStatus::from($this->string('status')->toString());
    }
}
