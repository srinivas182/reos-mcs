<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A learner's progress through a course (A8.2): seat time, lessons done and completion.
 *
 * @property int $id
 * @property int $user_id
 * @property int $course_id
 * @property int $counted_seconds
 * @property int $lessons_done
 * @property int $lessons_total
 * @property int $percent
 * @property Carbon|null $completed_at
 */
final class CourseProgress extends Model
{
    protected $table = 'course_progress';

    protected $fillable = ['user_id', 'course_id', 'counted_seconds', 'lessons_done', 'lessons_total', 'percent', 'completed_at'];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime', 'counted_seconds' => 'integer', 'lessons_done' => 'integer', 'lessons_total' => 'integer', 'percent' => 'integer'];
    }
}
