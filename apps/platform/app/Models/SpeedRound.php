<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A 60-second speed round (screen 50, A9.2d): true-or-false statements made
 * from questions, the taps, and the score.
 *
 * @property int $id
 * @property int $user_id
 * @property list<array{id: int, question_id: int, option: int, truth: bool, topic: string}> $statements
 * @property array<int, bool>|null $answers Statement id: tapped True
 * @property int|null $score
 * @property int|null $answered
 * @property array<string, array{correct: int, total: int}>|null $by_topic
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 */
final class SpeedRound extends Model
{
    /** @var list<string> */
    protected $fillable = ['user_id', 'statements', 'answers', 'score', 'answered', 'by_topic', 'started_at', 'finished_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['statements' => 'array', 'answers' => 'array', 'by_topic' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }
}
