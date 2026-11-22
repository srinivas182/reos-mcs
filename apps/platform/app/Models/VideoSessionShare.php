<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Something the host shared in a session (B6.3b quick actions): a lesson to
 * open, or practice questions to answer in the room.
 *
 * @property int $id
 * @property int $video_session_id
 * @property int|null $user_id
 * @property string $kind lesson or questions
 * @property array{lesson?: int, title?: string, course?: string, topic?: string, questions?: list<int>} $payload
 * @property CarbonImmutable $created_at
 * @property-read User|null $user
 */
final class VideoSessionShare extends Model
{
    public const LESSON = 'lesson';

    public const QUESTIONS = 'questions';

    public const UPDATED_AT = null;

    protected $fillable = ['video_session_id', 'user_id', 'kind', 'payload'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'created_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
