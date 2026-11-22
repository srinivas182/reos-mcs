<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Content\IngestStatus;
use App\Jobs\IngestLesson;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use App\Services\Audit\AuditLogger;

/**
 * Lessons (A5.3): their text, language and place in the course. A change to
 * the text or language rebuilds the lesson's passages. Every change is
 * audited.
 */
final readonly class Lessons
{
    public const AUDIT_SUBJECT = 'lesson';

    public function __construct(private AuditLogger $audit) {}

    /** @param  array{title: string, course_module_id?: int|null, language?: string|null}  $data */
    public function create(Course $course, array $data, User $actor): Lesson
    {
        $lesson = Lesson::query()->create([
            'course_id' => $course->id,
            'course_module_id' => $data['course_module_id'] ?? null,
            'title' => $data['title'],
            'language' => $data['language'] ?? 'en',
            'position' => self::next(Lesson::query()->where('course_id', $course->id)->max('position')),
        ]);

        $this->audit->record('lesson.created', self::AUDIT_SUBJECT, (string) $lesson->id, ['course' => $course->id, 'title' => $lesson->title], $actor);

        return $lesson;
    }

    private static function next(mixed $max): int
    {
        return (is_numeric($max) ? (int) $max : 0) + 1;
    }

    /** @param  array<string, mixed>  $data  title, body, language, minutes, course_module_id */
    public function update(Lesson $lesson, array $data, User $actor): Lesson
    {
        $lesson->fill(array_intersect_key($data, array_flip(['title', 'body', 'language', 'minutes', 'course_module_id'])));
        $changed = array_keys($lesson->getDirty());

        if ($changed === []) {
            return $lesson;
        }

        $reindex = $lesson->isDirty(['body', 'language']);

        if ($reindex) {
            $lesson->ingest_status = IngestStatus::Pending;
        }

        $lesson->save();
        $this->audit->record('lesson.updated', self::AUDIT_SUBJECT, (string) $lesson->id, ['changed' => $changed], $actor);

        if ($reindex) {
            IngestLesson::dispatch($lesson->id);
        }

        return $lesson;
    }

    /** Hidden from learners and search at once; Undo brings it back. */
    public function delete(Lesson $lesson, User $actor): void
    {
        $lesson->delete();
        $this->audit->record('lesson.deleted', self::AUDIT_SUBJECT, (string) $lesson->id, ['title' => $lesson->title], $actor);
    }

    public function restore(Lesson $lesson, User $actor): void
    {
        $lesson->restore();
        $this->audit->record('lesson.restored', self::AUDIT_SUBJECT, (string) $lesson->id, ['title' => $lesson->title], $actor);
    }

    /** "Read again": rebuild the passages now (after a fix, or once meaning search is switched on). */
    public function reindex(Lesson $lesson, User $actor): void
    {
        $lesson->forceFill(['ingest_status' => IngestStatus::Pending])->save();
        $this->audit->record('lesson.reindexed', self::AUDIT_SUBJECT, (string) $lesson->id, [], $actor);
        IngestLesson::dispatch($lesson->id);
    }
}
