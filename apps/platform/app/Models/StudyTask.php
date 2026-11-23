<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One thing to do on one day of a study plan (A8.3): the diagnostic, part of
 * a lesson, a topic quiz, or a review.
 *
 * @property int $id
 * @property int $study_plan_id
 * @property int $user_id
 * @property Carbon $day
 * @property int $position
 * @property string $kind diagnostic, lesson, practice or review
 * @property int|null $lesson_id
 * @property string|null $topic
 * @property int $minutes
 * @property string $why next, weak, review, catch_up or baseline
 * @property Carbon|null $done_at
 * @property-read Lesson|null $lesson
 */
final class StudyTask extends Model
{
    protected $fillable = ['study_plan_id', 'user_id', 'day', 'position', 'kind', 'lesson_id', 'topic', 'minutes', 'why', 'done_at'];

    protected function casts(): array
    {
        return ['day' => 'date', 'position' => 'integer', 'minutes' => 'integer', 'done_at' => 'datetime'];
    }

    /** @return BelongsTo<StudyPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(StudyPlan::class, 'study_plan_id');
    }

    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
