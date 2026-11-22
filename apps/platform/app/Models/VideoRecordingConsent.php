<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person's answer to "record this session?" (B6.3c).
 *
 * @property int $id
 * @property int $video_recording_id
 * @property int $user_id
 * @property bool $agreed
 * @property int|null $consent_record_id
 * @property CarbonImmutable $created_at
 * @property-read User $user
 */
final class VideoRecordingConsent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['video_recording_id', 'user_id', 'agreed', 'consent_record_id'];

    protected function casts(): array
    {
        return ['agreed' => 'boolean', 'created_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
