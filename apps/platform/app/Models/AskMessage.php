<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One message in an Ask conversation (A10.1): the person's question, the
 * study mate's answer, or their instructor's (A10.1b).
 *
 * @property int $id
 * @property int $ask_thread_id
 * @property string $role question, answer or instructor
 * @property string $body
 * @property string|null $tier content, sponsored or limited
 * @property string|null $mode ask, simpler, spanish or quiz
 * @property list<array{lesson_id: int, lesson: string, seconds: int|null, page: int|null, heading: string|null, text: string}>|null $sources
 * @property array{prompt: string, options: list<string>, correct: int, explanation: string}|null $quiz
 * @property string|null $math_kind
 * @property string|null $sponsor
 * @property Carbon|null $created_at
 * @property-read AskThread $thread
 */
final class AskMessage extends Model
{
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = ['ask_thread_id', 'role', 'body', 'tier', 'mode', 'sources', 'quiz', 'math_kind', 'sponsor'];

    /** @return BelongsTo<AskThread, $this> */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(AskThread::class, 'ask_thread_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['sources' => 'array', 'quiz' => 'array'];
    }
}
