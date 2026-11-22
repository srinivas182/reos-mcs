<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Content\CourseStatus;
use App\Content\CourseType;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\KbPassage;
use App\Models\Lesson;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Courses and their modules (A5.3, ADR 0020). A CE or qualifying course
 * can't be Live in a state without the regulator's approval number for it
 * (screen 63: "no labels without approval"). Every change is audited.
 */
final readonly class Courses
{
    public const AUDIT_SUBJECT = 'course';

    /** Fields whose before and after go in the audit log. */
    private const AUDITED = ['title', 'type', 'description', 'states', 'hours', 'approvals'];

    public function __construct(private AuditLogger $audit) {}

    /** @param  array{title: string, type: string, description?: string|null, states?: list<string>|null, hours?: float|int|string|null}  $data */
    public function create(array $data, User $actor): Course
    {
        $course = Course::query()->create([
            'title' => $data['title'],
            'slug' => $this->slug($data['title']),
            'type' => CourseType::from($data['type']),
            'description' => $data['description'] ?? null,
            'states' => self::states($data['states'] ?? null),
            'hours' => $data['hours'] ?? null,
            'status' => CourseStatus::Draft,
            'created_by' => $actor->id,
        ]);

        $this->audit->record('course.created', self::AUDIT_SUBJECT, (string) $course->id, ['title' => $course->title, 'type' => $course->type->value], $actor);

        return $course;
    }

    /** @param  array<string, mixed>  $data  title, type, description, states, hours, approvals */
    public function update(Course $course, array $data, User $actor): Course
    {
        $before = $this->snapshot($course);

        if (array_key_exists('type', $data) && is_string($data['type'])) {
            $course->type = CourseType::from($data['type']);
        }

        if (array_key_exists('states', $data)) {
            $course->states = self::states(is_array($data['states']) ? array_values(array_filter($data['states'], 'is_string')) : null);
        }

        if (array_key_exists('approvals', $data)) {
            $course->approvals = self::approvals($data['approvals'], $course->states ?? []);
        }

        $course->fill(array_intersect_key($data, array_flip(['title', 'description', 'hours'])));

        if ($course->status === CourseStatus::Live && $course->missingApprovals() !== []) {
            throw ValidationException::withMessages(['approvals' => __('content.errors.noApproval', ['states' => implode(', ', $course->missingApprovals())])]);
        }

        $after = $this->snapshot($course);
        $changed = array_keys(array_filter($after, static fn (mixed $value, string $key): bool => $value !== $before[$key], ARRAY_FILTER_USE_BOTH));

        if ($changed === []) {
            return $course;
        }

        DB::transaction(function () use ($course, $changed, $before, $after, $actor): void {
            $course->save();

            // Passages carry the course's states, so search filters fast.
            if (in_array('states', $changed, true)) {
                KbPassage::query()->where('course_id', $course->id)->update(['states' => $course->states === null ? null : json_encode($course->states)]);
            }

            $this->audit->record('course.updated', self::AUDIT_SUBJECT, (string) $course->id, [
                'before' => array_intersect_key($before, array_flip($changed)),
                'after' => array_intersect_key($after, array_flip($changed)),
            ], $actor);
        });

        return $course;
    }

    /** Draft, in review, live or hidden. Live needs an approval in every state for CE and qualifying courses. */
    public function setStatus(Course $course, CourseStatus $status, User $actor, ?string $note = null): Course
    {
        $from = $course->status;

        if ($status === CourseStatus::Live) {
            if ($course->type->needsApproval() && ($course->states ?? []) === []) {
                throw ValidationException::withMessages(['status' => __('content.errors.needsStates')]);
            }

            $missing = $course->missingApprovals();

            if ($missing !== []) {
                throw ValidationException::withMessages(['status' => __('content.errors.noApproval', ['states' => implode(', ', $missing)])]);
            }
        }

        $course->forceFill([
            'status' => $status,
            'status_note' => $note,
            'published_at' => $status === CourseStatus::Live ? ($course->published_at ?? now()) : $course->published_at,
        ])->save();

        if ($from !== $status) {
            $this->audit->record('course.status_changed', self::AUDIT_SUBJECT, (string) $course->id, ['from' => $from->value, 'to' => $status->value], $actor);
        }

        return $course;
    }

    public function delete(Course $course, User $actor): void
    {
        $course->delete();
        $this->audit->record('course.deleted', self::AUDIT_SUBJECT, (string) $course->id, ['title' => $course->title], $actor);
    }

    public function restore(Course $course, User $actor): void
    {
        $course->restore();
        $this->audit->record('course.restored', self::AUDIT_SUBJECT, (string) $course->id, ['title' => $course->title], $actor);
    }

    public function addModule(Course $course, string $title, User $actor): CourseModule
    {
        $module = $course->modules()->create(['title' => $title, 'position' => self::next($course->modules()->max('position'))]);
        $this->audit->record('course.module_added', self::AUDIT_SUBJECT, (string) $course->id, ['module' => $title], $actor);

        return $module;
    }

    public function renameModule(CourseModule $module, string $title, User $actor): void
    {
        $from = $module->title;
        $module->forceFill(['title' => $title])->save();
        $this->audit->record('course.module_renamed', self::AUDIT_SUBJECT, (string) $module->course_id, ['from' => $from, 'to' => $title], $actor);
    }

    /** Its lessons stay in the course, outside any module. */
    public function removeModule(CourseModule $module, User $actor): void
    {
        DB::transaction(static function () use ($module): void {
            Lesson::withTrashed()->where('course_module_id', $module->id)->update(['course_module_id' => null]);
            $module->delete();
        });

        $this->audit->record('course.module_removed', self::AUDIT_SUBJECT, (string) $module->course_id, ['module' => $module->title], $actor);
    }

    private static function next(mixed $max): int
    {
        return (is_numeric($max) ? (int) $max : 0) + 1;
    }

    private function slug(string $title): string
    {
        $base = Str::slug($title) ?: 'course';
        $slug = $base;
        $n = 2;

        while (Course::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }

    /** @return array<string, mixed> */
    private function snapshot(Course $course): array
    {
        $values = [
            'title' => $course->title,
            'type' => $course->type->value,
            'description' => $course->description,
            'states' => $course->states,
            'hours' => $course->hours === null ? null : (string) (float) $course->hours,
            'approvals' => $course->approvals,
        ];

        return array_intersect_key($values, array_flip(self::AUDITED));
    }

    /**
     * @param  list<string>|null  $states
     * @return list<string>|null null for every state
     */
    private static function states(?array $states): ?array
    {
        if ($states === null || $states === []) {
            return null;
        }

        $codes = array_values(array_unique(array_map(static fn (string $code): string => strtoupper($code), $states)));
        sort($codes);

        return $codes;
    }

    /**
     * Approvals for the course's states only, numbers trimmed; empty ones dropped.
     *
     * @param  list<string>  $states
     * @return array<string, array{number: string, expires_on: string|null}>|null
     */
    private static function approvals(mixed $input, array $states): ?array
    {
        if (! is_array($input)) {
            return null;
        }

        $approvals = [];

        foreach ($input as $code => $approval) {
            $code = strtoupper((string) $code);

            if (! in_array($code, $states, true) || ! is_array($approval)) {
                continue;
            }

            $number = is_string($approval['number'] ?? null) ? trim($approval['number']) : '';
            $expires = $approval['expires_on'] ?? null;

            if ($number !== '') {
                $approvals[$code] = ['number' => $number, 'expires_on' => is_string($expires) && $expires !== '' ? $expires : null];
            }
        }

        ksort($approvals);

        return $approvals === [] ? null : $approvals;
    }
}
