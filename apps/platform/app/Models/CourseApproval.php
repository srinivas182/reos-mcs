<?php

declare(strict_types=1);

namespace App\Models;

use App\Content\Delivery;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A course's approval in one state for one delivery method (A7.1, ADR 0025;
 * school admin web screen 50). Only an approved, unexpired approval lets the
 * course carry its CE or qualifying label in that state.
 *
 * @property int $id
 * @property int $course_id
 * @property string $state_code
 * @property Delivery $delivery
 * @property string|null $number
 * @property string|null $hours
 * @property CarbonImmutable|null $approved_on
 * @property CarbonImmutable|null $expires_on
 * @property string $status
 * @property string|null $max_hours_per_day
 * @property int|null $checkin_minutes
 * @property int|null $presence_percent
 * @property int|null $makeup_min_percent
 * @property int|null $makeup_days
 * @property string|null $note
 * @property int|null $reminded_days
 * @property Carbon|null $expired_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Course $course
 */
final class CourseApproval extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const WITHDRAWN = 'withdrawn';

    /** What screen 50 shows: the stored status, or expired once the date has passed. */
    public const EXPIRED = 'expired';

    /** Reminders before an approval expires, in days. */
    public const REMIND_DAYS = [60, 30];

    protected $fillable = [
        'course_id', 'state_code', 'delivery', 'number', 'hours', 'approved_on', 'expires_on', 'status',
        'max_hours_per_day', 'checkin_minutes', 'presence_percent', 'makeup_min_percent', 'makeup_days', 'note', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'delivery' => Delivery::class,
            'hours' => 'decimal:1',
            'max_hours_per_day' => 'decimal:1',
            'approved_on' => 'immutable_date',
            'expires_on' => 'immutable_date',
            'checkin_minutes' => 'integer',
            'presence_percent' => 'integer',
            'makeup_min_percent' => 'integer',
            'makeup_days' => 'integer',
            'reminded_days' => 'integer',
            'expired_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** Approved, with a number, in force on the day (approved on or before it; not past its expiry). */
    public function valid(?CarbonImmutable $on = null): bool
    {
        $on = ($on ?? CarbonImmutable::today())->startOfDay();

        return $this->status === self::APPROVED
            && trim((string) $this->number) !== ''
            && ($this->approved_on === null || $this->approved_on->lte($on))
            && ($this->expires_on === null || $this->expires_on->gte($on));
    }

    /** pending, approved, withdrawn, or expired once the date has passed. */
    public function shownStatus(?CarbonImmutable $on = null): string
    {
        $on = ($on ?? CarbonImmutable::today())->startOfDay();

        return $this->status === self::APPROVED && $this->expires_on !== null && $this->expires_on->lt($on) ? self::EXPIRED : $this->status;
    }

    /** Days until it expires (negative once expired), or null when it doesn't. */
    public function daysLeft(?CarbonImmutable $on = null): ?int
    {
        $on = ($on ?? CarbonImmutable::today())->startOfDay();

        return $this->expires_on === null ? null : (int) $on->diffInDays($this->expires_on, false);
    }
}
