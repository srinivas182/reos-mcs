<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Content;

use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\In;

/**
 * A new lesson, or changes to one (A5.3). The module must be in the same
 * course. Lesson text is plain text; lines starting with "# " are headings.
 */
final class LessonRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|In|Exists|string>> */
    public function rules(): array
    {
        $creating = $this->isMethod('POST');
        $course = $this->route('course');
        $lesson = $this->route('lesson');
        $courseId = $course instanceof Course ? $course->id : ($lesson instanceof Lesson ? $lesson->course_id : 0);

        return [
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'min:2', 'max:200'],
            'course_module_id' => ['sometimes', 'nullable', 'integer', Rule::exists('course_modules', 'id')->where('course_id', $courseId)],
            'language' => ['sometimes', Rule::in(Lesson::LANGUAGES)],
            'body' => ['sometimes', 'nullable', 'string', 'max:200000'],
            'minutes' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:600'],
        ];
    }
}
