<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Content\CourseStatus;
use App\Content\CourseType;
use App\Content\Review\ContentChecks;
use App\Models\Course;
use App\Models\CourseApproval;
use App\Models\CourseModule;
use App\Models\KbPassage;
use App\Models\Lesson;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
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
    private const AUDITED = ['title', 'type', 'description', 'states', 'hours', 'skills', 'modes', 'approvals'];

    public function __construct(private AuditLogger $audit, private ContentChecks $checks) {}

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

        $numbers = array_key_exists('approvals', $data) ? self::approvals($data['approvals'], $course->states ?? []) : null;

        $course->fill(array_intersect_key($data, array_flip(['title', 'description', 'hours'])));

        // What it teaches and who it suits, for course picks (A14.2).
        foreach (['skills', 'modes'] as $key) {
            if (array_key_exists($key, $data)) {
                $list = is_array($data[$key]) ? array_values(array_unique(array_filter($data[$key], 'is_string'))) : [];
                $course->{$key} = $list === [] ? null : $list;
            }
        }

        if ($numbers !== null) {
            $course->setRelation('approvals', $this->withNumbers($course, $numbers));
        }

        if ($course->status === CourseStatus::Live && $course->missingApprovals() !== []) {
            throw ValidationException::withMessages(['approvals' => __('content.errors.noApproval', ['states' => implode(', ', $course->missingApprovals())])]);
        }

        $after = $this->snapshot($course);
        $changed = array_keys(array_filter($after, static fn (mixed $value, string $key): bool => $value !== $before[$key], ARRAY_FILTER_USE_BOTH));

        if ($changed === []) {
            return $course;
        }

        DB::transaction(function () use ($course, $changed, $before, $after, $actor, $numbers): void {
            $course->save();

            if ($numbers !== null && in_array('approvals', $changed, true)) {
                $this->saveNumbers($course, $numbers, $actor);
            }

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

            $open = $this->checks->for($course)['open'];

            if ($open > 0) {
                throw ValidationException::withMessages(['status' => __('content.errors.flagged', ['count' => $open])]);
            }
        }

        $reviewed = $from === CourseStatus::InReview && $status !== CourseStatus::InReview;

        $course->forceFill([
            'status' => $status,
            'status_note' => $note,
            'reviewed_by' => $reviewed ? $actor->id : $course->reviewed_by,
            'reviewed_at' => $reviewed ? now() : $course->reviewed_at,
            'published_at' => $status === CourseStatus::Live ? ($course->published_at ?? now()) : $course->published_at,
        ])->save();

        if ($from !== $status) {
            $this->audit->record('course.status_changed', self::AUDIT_SUBJECT, (string) $course->id, array_filter(['from' => $from->value, 'to' => $status->value, 'note' => $note]), $actor);
        }

        return $course;
    }

    /** A reviewer checked a flagged line and it's fine in context: it stops blocking the course. */
    public function clearFlag(Course $course, string $key, User $actor): void
    {
        $flag = collect($this->checks->for($course)['flags'])->firstWhere('key', $key);

        if ($flag === null) {
            throw ValidationException::withMessages(['flag' => __('content.errors.noFlag')]);
        }

        $cleared = array_values(array_filter($course->cleared_flags ?? [], static fn (array $c): bool => $c['key'] !== $key));
        $cleared[] = ['key' => $key, 'by' => $actor->id, 'at' => now()->toIso8601String()];
        $course->forceFill(['cleared_flags' => $cleared])->save();

        $this->audit->record('course.flag_cleared', self::AUDIT_SUBJECT, (string) $course->id, ['rule' => $flag['rule'], 'lesson' => $flag['lesson_id'], 'excerpt' => $flag['excerpt']], $actor);
    }

    /** Undo: the line blocks the course again. */
    public function restoreFlag(Course $course, string $key, User $actor): void
    {
        $cleared = array_values(array_filter($course->cleared_flags ?? [], static fn (array $c): bool => $c['key'] !== $key));
        $course->forceFill(['cleared_flags' => $cleared === [] ? null : $cleared])->save();

        $this->audit->record('course.flag_restored', self::AUDIT_SUBJECT, (string) $course->id, ['key' => $key], $actor);
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
            'skills' => $course->skills,
            'modes' => $course->modes,
            'approvals' => $course->approvalNumbers(),
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
     * The course's approvals as they'd be with these numbers (unsaved), so
     * the Live check sees the change before anything is written.
     *
     * @param  array<string, array{number: string, expires_on: string|null}>  $numbers
     * @return Collection<int, CourseApproval>
     */
    private function withNumbers(Course $course, array $numbers): Collection
    {
        $approvals = $course->approvals->map(static fn (CourseApproval $a): CourseApproval => clone $a);

        foreach ($approvals as $approval) {
            if ($approval->delivery === $course->delivery && $approval->status === CourseApproval::APPROVED && ! isset($numbers[$approval->state_code])) {
                $approval->status = CourseApproval::WITHDRAWN;
            }
        }

        foreach ($numbers as $state => $entry) {
            $existing = $approvals->first(static fn (CourseApproval $a): bool => $a->state_code === $state && $a->delivery === $course->delivery);
            $approval = $existing ?? new CourseApproval(['course_id' => $course->id, 'state_code' => $state, 'delivery' => $course->delivery]);
            $approval->fill(['number' => $entry['number'], 'expires_on' => $entry['expires_on'], 'status' => CourseApproval::APPROVED]);

            if ($existing === null) {
                $approvals->push($approval);
            }
        }

        return $approvals;
    }

    /**
     * Saves the content library's approval numbers (A5.3 quick edit) as
     * course approvals for the course's delivery; a cleared number withdraws it.
     *
     * @param  array<string, array{number: string, expires_on: string|null}>  $numbers
     */
    private function saveNumbers(Course $course, array $numbers, User $actor): void
    {
        foreach ($course->approvals()->where('delivery', $course->delivery->value)->where('status', CourseApproval::APPROVED)->get() as $approval) {
            if (! isset($numbers[$approval->state_code])) {
                $approval->forceFill(['status' => CourseApproval::WITHDRAWN])->save();
            }
        }

        foreach ($numbers as $state => $entry) {
            $approval = $course->approvals()->firstOrNew(['state_code' => $state, 'delivery' => $course->delivery->value]);
            $changedExpiry = $approval->exists && $approval->expires_on?->toDateString() !== $entry['expires_on'];
            $approval->fill(['number' => $entry['number'], 'expires_on' => $entry['expires_on'], 'status' => CourseApproval::APPROVED]);
            $approval->hours ??= $course->hours;
            $approval->created_by ??= $actor->id;

            if ($changedExpiry) {
                $approval->forceFill(['reminded_days' => null, 'expired_at' => null]);
            }

            $approval->save();
        }

        $course->unsetRelation('approvals');
    }

    /**
     * Approvals for the course's states only, numbers trimmed; empty ones dropped.
     *
     * @param  list<string>  $states
     * @return array<string, array{number: string, expires_on: string|null}>
     */
    private static function approvals(mixed $input, array $states): array
    {
        if (! is_array($input)) {
            return [];
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

        return $approvals;
    }
}
