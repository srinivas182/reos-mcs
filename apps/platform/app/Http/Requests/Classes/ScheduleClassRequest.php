<?php

declare(strict_types=1);

namespace App\Http\Requests\Classes;

use App\Models\LiveClass;
use App\Video\Classes\LiveClasses;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

/** Schedule a live class (A10.2b, instructor i11; web w505). */
final class ScheduleClassRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|In|string>> */
    public function rules(): array
    {
        return [
            /** A course you teach or your school owns (from `courses`). */
            'course_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'min:3', 'max:120'],
            'agenda' => ['nullable', 'string', 'max:2000'],
            /** The start (ISO 8601). */
            'starts_at' => ['required', 'date'],
            /**
             * Length in minutes: 30, 45, 60, 90, 120, 180 or 240.
             *
             * @var int
             */
            'minutes' => ['required', 'integer', Rule::in(LiveClasses::LENGTHS)],
            /** Most students who can register (1–100). */
            'capacity' => ['required', 'integer', 'min:1', 'max:'.LiveClass::MAX_CAPACITY],
        ];
    }

    /** @return array{course_id: int, title: string, agenda: string|null, starts_at: CarbonImmutable, minutes: int, capacity: int} */
    public function plan(): array
    {
        $agenda = trim($this->string('agenda')->toString());

        return [
            'course_id' => $this->integer('course_id'),
            'title' => trim($this->string('title')->toString()),
            'agenda' => $agenda === '' ? null : $agenda,
            'starts_at' => CarbonImmutable::parse($this->string('starts_at')->toString())->utc(),
            'minutes' => $this->integer('minutes'),
            'capacity' => $this->integer('capacity'),
        ];
    }
}
