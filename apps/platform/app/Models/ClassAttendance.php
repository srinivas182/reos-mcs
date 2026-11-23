<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One student's line on a CE class report (A10.2c): seat time, checks
 * answered, the outcome by the approval's rules, and the instructor's
 * decision on a borderline case.
 *
 * @property int $id
 * @property int $video_session_id
 * @property int $user_id
 * @property int $seconds_present
 * @property int $checks_answered
 * @property int $checks_total
 * @property string $outcome earned, review or not_earned
 * @property list<string> $reasons seat_time, checks, not_joined
 * @property string|null $decision accepted or rejected
 * @property string|null $decision_reason
 * @property int|null $decided_by
 * @property CarbonImmutable|null $decided_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 */
final class ClassAttendance extends Model
{
    public const EARNED = 'earned';

    public const REVIEW = 'review';

    public const NOT_EARNED = 'not_earned';

    public const ACCEPTED = 'accepted';

    public const REJECTED = 'rejected';

    protected $table = 'class_attendance';

    protected $fillable = ['video_session_id', 'user_id', 'seconds_present', 'checks_answered', 'checks_total', 'outcome', 'reasons', 'decision', 'decision_reason', 'decided_by', 'decided_at'];

    protected function casts(): array
    {
        return ['seconds_present' => 'integer', 'checks_answered' => 'integer', 'checks_total' => 'integer', 'reasons' => 'array', 'decided_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Earned CE: by the rules, or accepted on review. */
    public function earned(): bool
    {
        return $this->outcome === self::EARNED || ($this->outcome === self::REVIEW && $this->decision === self::ACCEPTED);
    }
}
