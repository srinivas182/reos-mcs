<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A chat message in a live class (A10.2b): everyone in the class sees it.
 *
 * @property int $id
 * @property int $video_session_id
 * @property int $user_id
 * @property string $body
 * @property CarbonImmutable $created_at
 * @property-read User $user
 */
final class ClassMessage extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['video_session_id', 'user_id', 'body'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
