<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A student's question in a live class (A10.2b): to the instructor's
 * queue, where classmates' likes move it up.
 *
 * @property int $id
 * @property int $video_session_id
 * @property int $user_id
 * @property string $body
 * @property int $likes
 * @property CarbonImmutable|null $answered_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 */
final class ClassQuestion extends Model
{
    protected $fillable = ['video_session_id', 'user_id', 'body', 'likes', 'answered_at'];

    protected function casts(): array
    {
        return ['likes' => 'integer', 'answered_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
