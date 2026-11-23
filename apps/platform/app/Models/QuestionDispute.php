<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A learner reports a question (A8.1): wrong answer, unclear, out of date or
 * another reason. The question's owner keeps it or corrects it, and the
 * learner is told.
 *
 * @property int $id
 * @property int $quiz_question_id
 * @property int $user_id
 * @property string $reason wrong_answer, unclear, out_of_date or other
 * @property string|null $details
 * @property string $status open, kept or corrected
 * @property string|null $resolution
 * @property int|null $resolved_by
 * @property Carbon|null $resolved_at
 * @property Carbon|null $created_at
 * @property-read QuizQuestion $question
 * @property-read User $user
 */
final class QuestionDispute extends Model
{
    public const REASONS = ['wrong_answer', 'unclear', 'out_of_date', 'other'];

    protected $fillable = ['quiz_question_id', 'user_id', 'reason', 'details', 'status', 'resolution', 'resolved_by', 'resolved_at'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    /** @return BelongsTo<QuizQuestion, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class, 'quiz_question_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
