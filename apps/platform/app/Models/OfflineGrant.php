<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A lesson saved on one phone (A8.2b, ADR 0030): valid 30 days between online
 * checks, and only while the learner still has the course. The key signs the
 * phone's offline seat time; it never leaves the server except to that phone.
 *
 * @property int $id
 * @property int $user_id
 * @property int $lesson_id
 * @property string $device
 * @property string $key
 * @property int|null $bytes
 * @property Carbon $expires_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $created_at
 * @property-read Lesson $lesson
 * @property-read User $user
 */
final class OfflineGrant extends Model
{
    protected $fillable = ['user_id', 'lesson_id', 'device', 'key', 'bytes', 'expires_at', 'revoked_at'];

    protected $hidden = ['key'];

    protected function casts(): array
    {
        return ['key' => 'encrypted', 'expires_at' => 'datetime', 'revoked_at' => 'datetime', 'bytes' => 'integer'];
    }

    public function usable(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }

    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
