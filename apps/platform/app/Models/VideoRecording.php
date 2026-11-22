<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A session recording (B6.3c): only with everyone's agreement, kept 90 days.
 *
 * @property int $id
 * @property string $uuid
 * @property int $video_session_id
 * @property int|null $requested_by
 * @property string $status asking, declined, recording, stopping, ready, failed or deleted
 * @property string|null $egress_id
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $stopped_at
 * @property int|null $stopped_by
 * @property string|null $path
 * @property int|null $duration_seconds
 * @property int|null $size
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $deleted_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read VideoSession $session
 * @property-read User|null $requester
 * @property-read Collection<int, VideoRecordingConsent> $consents
 */
final class VideoRecording extends Model
{
    public const ASKING = 'asking';

    public const DECLINED = 'declined';

    public const RECORDING = 'recording';

    public const STOPPING = 'stopping';

    public const READY = 'ready';

    public const FAILED = 'failed';

    public const DELETED = 'deleted';

    /** Recordings are deleted this many days after the session (decision of 22 November 2026). */
    public const KEEP_DAYS = 90;

    protected $fillable = ['uuid', 'video_session_id', 'requested_by', 'status', 'egress_id', 'started_at', 'stopped_at', 'stopped_by', 'path', 'duration_seconds', 'size', 'expires_at', 'deleted_at'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'stopped_at' => 'datetime',
            'expires_at' => 'datetime',
            'deleted_at' => 'datetime',
            'duration_seconds' => 'integer',
            'size' => 'integer',
        ];
    }

    /** @return BelongsTo<VideoSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(VideoSession::class, 'video_session_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return HasMany<VideoRecordingConsent, $this> */
    public function consents(): HasMany
    {
        return $this->hasMany(VideoRecordingConsent::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function active(): bool
    {
        return in_array($this->status, [self::ASKING, self::RECORDING, self::STOPPING], true);
    }

    /** The consent record's subject: one recording of one session. */
    public function consentSubject(): string
    {
        return 'recording:'.$this->uuid;
    }
}
