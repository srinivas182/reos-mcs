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
 * @property bool $in_room In the room right now (B6.3c)
 * @property CarbonImmutable|null $hand_raised_at A live class guest raised their hand (A10.2b)
 * @property bool $on_stage The host brought them on: they may speak and show video
 * @property int $seconds_present Seat time: seconds in the room between the booked start and end
 * @property-read User $user
 * @property-read VideoSession $session
 */
final class VideoSessionParticipant extends Model
{
    public const HOST = 'host';

    public const GUEST = 'guest';

    public $timestamps = false;

    protected $fillable = ['video_session_id', 'user_id', 'role', 'first_joined_at', 'last_joined_at', 'joins', 'in_room', 'hand_raised_at', 'on_stage', 'seconds_present'];

    protected function casts(): array
    {
        return ['first_joined_at' => 'datetime', 'last_joined_at' => 'datetime', 'joins' => 'integer', 'in_room' => 'boolean', 'hand_raised_at' => 'datetime', 'on_stage' => 'boolean', 'seconds_present' => 'integer'];
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
