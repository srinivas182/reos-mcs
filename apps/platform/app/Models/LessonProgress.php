<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A learner's seat time and place in one lesson (A8.2, ADR 0030).
 *
 * @property int $id
 * @property int $user_id
 * @property int $lesson_id
 * @property int $counted_seconds
 * @property int $required_seconds
 * @property int $position
 * @property array<string, array{correct: bool|null, at: string}>|null $checkins
 * @property string|null $session
 * @property Carbon|null $last_beat_at
 * @property Carbon|null $completed_at
 * @property-read Lesson $lesson
 */
final class LessonProgress extends Model
{
    protected $table = 'lesson_progress';

    protected $fillable = ['user_id', 'lesson_id', 'counted_seconds', 'required_seconds', 'position', 'checkins', 'session', 'last_beat_at', 'completed_at'];

    protected function casts(): array
    {
        return ['checkins' => 'array', 'last_beat_at' => 'datetime', 'completed_at' => 'datetime', 'counted_seconds' => 'integer', 'required_seconds' => 'integer', 'position' => 'integer'];
    }

    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
