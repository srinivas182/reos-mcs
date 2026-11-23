<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A candidate's study plan up to the exam (A8.3, ADR 0032). Rebuilt when they
 * miss a day, change minutes a day or the exam month, or finish a test; the
 * old plan is kept, inactive.
 *
 * @property int $id
 * @property int $user_id
 * @property Carbon $exam_on
 * @property int $minutes_per_day
 * @property list<string>|null $weak_topics
 * @property string $reason
 * @property array<string, int>|null $calendar_events
 * @property bool $active
 * @property Carbon|null $created_at
 */
final class StudyPlan extends Model
{
    protected $fillable = ['user_id', 'exam_on', 'minutes_per_day', 'weak_topics', 'reason', 'calendar_events', 'active'];

    protected function casts(): array
    {
        return ['exam_on' => 'date', 'minutes_per_day' => 'integer', 'weak_topics' => 'array', 'calendar_events' => 'array', 'active' => 'boolean'];
    }

    /** @return HasMany<StudyTask, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(StudyTask::class)->orderBy('day')->orderBy('position');
    }
}
