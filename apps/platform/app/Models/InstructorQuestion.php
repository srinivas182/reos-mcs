<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A question sent from Ask to a course's instructors (A10.1b).
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property int $course_id
 * @property int|null $lesson_id
 * @property int|null $ask_thread_id
 * @property string $question
 * @property string|null $content_answer
 * @property string $language
 * @property string $status waiting or answered
 * @property string|null $answer
 * @property int|null $answered_by
 * @property Carbon|null $answered_at
 * @property int|null $kb_passage_id
 * @property bool $flashcard
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Course $course
 * @property-read Lesson|null $lesson
 * @property-read AskThread|null $thread
 * @property-read User|null $answerer
 */
final class InstructorQuestion extends Model
{
    /** @var list<string> */
    protected $fillable = ['uuid', 'user_id', 'course_id', 'lesson_id', 'ask_thread_id', 'question', 'content_answer', 'language', 'status', 'answer', 'answered_by', 'answered_at', 'kb_passage_id', 'flashcard'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class)->withTrashed();
    }

    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /** @return BelongsTo<AskThread, $this> */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(AskThread::class, 'ask_thread_id');
    }

    /** @return BelongsTo<User, $this> */
    public function answerer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['answered_at' => 'datetime', 'flashcard' => 'boolean'];
    }
}
