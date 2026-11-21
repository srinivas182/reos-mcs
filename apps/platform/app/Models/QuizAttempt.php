<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A finished quiz. The free quiz is answered before sign-up, kept on the
 * device, and attached to the account when it's created.
 *
 * @property int $id
 * @property int $user_id
 * @property string $kind
 * @property string|null $state_code
 * @property list<array{question_id: int, choice: int, correct: bool}> $answers
 * @property int $correct
 * @property int $total
 * @property Carbon $completed_at
 */
final class QuizAttempt extends Model
{
    /** @var list<string> */
    protected $fillable = ['user_id', 'kind', 'state_code', 'answers', 'correct', 'total', 'completed_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['answers' => 'array', 'completed_at' => 'datetime', 'correct' => 'integer', 'total' => 'integer'];
    }
}
