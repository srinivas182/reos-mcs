<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One go at a test (A8.3, ADR 0032): the diagnostic or a topic quiz. Saved
 * after every answer; a timed test ends at `ends_at` whether or not the
 * person is still there.
 *
 * @property int $id
 * @property int $user_id
 * @property string $kind diagnostic or topic
 * @property string $mode exam or practice
 * @property string|null $state_code
 * @property string|null $topic
 * @property list<int> $question_ids
 * @property array<int, int>|null $answers {question id: choice}
 * @property list<int>|null $flags
 * @property Carbon $started_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $submitted_at
 * @property int|null $correct
 * @property int $total
 * @property int|null $score
 * @property array<string, array{correct: int, total: int}>|null $by_topic
 * @property-read User $user
 */
final class TestAttempt extends Model
{
    public const DIAGNOSTIC = 'diagnostic';

    public const TOPIC = 'topic';

    protected $fillable = ['user_id', 'kind', 'mode', 'state_code', 'topic', 'question_ids', 'answers', 'flags', 'started_at', 'ends_at', 'submitted_at', 'correct', 'total', 'score', 'by_topic'];

    protected function casts(): array
    {
        return [
            'question_ids' => 'array', 'answers' => 'array', 'flags' => 'array', 'by_topic' => 'array',
            'started_at' => 'datetime', 'ends_at' => 'datetime', 'submitted_at' => 'datetime',
            'correct' => 'integer', 'total' => 'integer', 'score' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
