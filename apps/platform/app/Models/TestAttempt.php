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
 * @property string $kind diagnostic, topic, national, state, simulation, drill or retest
 * @property string $mode exam or practice
 * @property string|null $state_code
 * @property string|null $topic
 * @property int|null $challenge_id A challenge's attempt (A9.3b)
 * @property list<int> $question_ids
 * @property array<int, int>|null $answers {question id: choice}
 * @property list<int>|null $flags
 * @property list<int>|null $hints Questions a hint was taken on (smart drill, A9.2b)
 * @property Carbon $started_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $submitted_at
 * @property int|null $correct
 * @property int $total
 * @property int|null $score
 * @property array<string, array{correct: int, total: int}>|null $by_topic
 * @property list<array{portion: string, from: int, count: int, scored: int, minutes: int, pass: int, started_at: string|null, ends_at: string|null, ended_at: string|null}>|null $sections Timed portions (A9.1)
 * @property list<int>|null $unscored Pretest items: asked, not scored
 * @property Carbon|null $paused_at
 * @property array<string, array{correct: int, total: int, pass: int, passed: bool}>|null $by_portion
 * @property-read User $user
 * @property-read Challenge|null $challenge
 */
final class TestAttempt extends Model
{
    public const DIAGNOSTIC = 'diagnostic';

    public const TOPIC = 'topic';

    /** The National portion test (A9.1). */
    public const NATIONAL = 'national';

    /** The state portion test (A9.1). */
    public const STATE = 'state';

    /** Both portions back to back, each timed (A9.1). */
    public const SIMULATION = 'simulation';

    public const PORTION_KINDS = [self::NATIONAL, self::STATE, self::SIMULATION];

    /** The smart drill (A9.2b): the weakest topics and recent misses, with hints and why; or one topic's, in a fix plan. */
    public const DRILL = 'drill';

    /** A fix plan's retest (A9.2b): new questions on one topic, why at the end. */
    public const RETEST = 'retest';

    /** A challenge (A9.3b): the challenge's 10 questions, results at the end, hints cost points. */
    public const CHALLENGE = 'challenge';

    protected $fillable = ['user_id', 'kind', 'mode', 'state_code', 'topic', 'challenge_id', 'question_ids', 'answers', 'flags', 'hints', 'started_at', 'ends_at', 'submitted_at', 'correct', 'total', 'score', 'by_topic', 'sections', 'unscored', 'paused_at', 'by_portion'];

    protected function casts(): array
    {
        return [
            'question_ids' => 'array', 'answers' => 'array', 'flags' => 'array', 'hints' => 'array', 'by_topic' => 'array', 'sections' => 'array', 'unscored' => 'array', 'by_portion' => 'array', 'paused_at' => 'datetime',
            'started_at' => 'datetime', 'ends_at' => 'datetime', 'submitted_at' => 'datetime',
            'correct' => 'integer', 'total' => 'integer', 'score' => 'integer',
        ];
    }

    /** @return BelongsTo<Challenge, $this> */
    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
