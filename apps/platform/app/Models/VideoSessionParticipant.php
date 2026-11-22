<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Someone in a video session (B6.3): the host, or a guest.
 *
 * @property int $id
 * @property int $video_session_id
 * @property int $user_id
 * @property string $role host or guest
 * @property CarbonImmutable|null $first_joined_at
 * @property CarbonImmutable|null $last_joined_at
 * @property int $joins
 * @property-read User $user
 * @property-read VideoSession $session
 */
final class VideoSessionParticipant extends Model
{
    public const HOST = 'host';

    public const GUEST = 'guest';

    public $timestamps = false;

    protected $fillable = ['video_session_id', 'user_id', 'role', 'first_joined_at', 'last_joined_at', 'joins'];

    protected function casts(): array
    {
        return ['first_joined_at' => 'datetime', 'last_joined_at' => 'datetime', 'joins' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<VideoSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(VideoSession::class, 'video_session_id');
    }
}
