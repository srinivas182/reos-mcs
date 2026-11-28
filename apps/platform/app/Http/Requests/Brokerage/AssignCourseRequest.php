<?php

declare(strict_types=1);

namespace App\Http\Requests\Brokerage;

use Illuminate\Foundation\Http\FormRequest;

/** Assign a course to agents, or to everyone due (A16.2). */
final class AssignCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'course_id' => ['required', 'integer'],
            /** Agents' ids; leave out with `due: true` for everyone due. */
            'agents' => ['required_without:due', 'array', 'max:500'],
            'agents.*' => ['integer'],
            'due' => ['sometimes', 'boolean'],
            'due_on' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }

    /** @return list<int>|'due' */
    public function agents(): array|string
    {
        if ($this->boolean('due')) {
            return 'due';
        }

        $ids = $this->validated('agents');

        return is_array($ids) ? array_values(array_map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, $ids)) : [];
    }
}
