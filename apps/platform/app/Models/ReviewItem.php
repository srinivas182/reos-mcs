<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A check-in, question or flashcard drafted with an AI lesson (A7.3),
 * waiting for the instructor to approve, edit or reject it (A8.1).
 *
 * @property int $id
 * @property int $lesson_id
 * @property int $lesson_version_id
 * @property string $kind checkin, question or flashcard
 * @property int|null $after_line
 * @property string|null $topic
 * @property string|null $difficulty
 * @property string|null $portion national or state (with the topic, the exam-outline mapping)
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property array<string, mixed> $content
 * @property string $status to_review, approved or rejected
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Lesson $lesson
 */
final class ReviewItem extends Model
{
    public const TO_REVIEW = 'to_review';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    protected $fillable = ['lesson_id', 'lesson_version_id', 'kind', 'after_line', 'topic', 'difficulty', 'portion', 'content', 'status', 'reviewed_by', 'reviewed_at'];

    protected function casts(): array
    {
        return ['content' => 'array', 'after_line' => 'integer', 'reviewed_at' => 'datetime'];
    }

    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
