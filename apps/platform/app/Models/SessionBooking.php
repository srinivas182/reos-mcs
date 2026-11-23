<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A booked 1-on-1 session (A10.2a): held while it's being paid for, then
 * confirmed with its video session.
 *
 * @property int $id
 * @property string $uuid
 * @property int $student_id
 * @property int $instructor_id
 * @property int|null $video_session_id
 * @property int|null $order_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property int $minutes
 * @property string $topic
 * @property string $status held, confirmed, cancelled, refunded, completed or no_show
 * @property int $price_cents
 * @property Carbon|null $held_until
 * @property Carbon|null $cancelled_at
 * @property int|null $cancelled_by
 * @property Carbon|null $refunded_at
 * @property int|null $rating
 * @property list<string>|null $rating_tags
 * @property string|null $rating_comment
 * @property Carbon|null $rated_at
 * @property Carbon|null $tasks_sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $student
 * @property-read User $instructor
 * @property-read VideoSession|null $session
 * @property-read Order|null $order
 */
final class SessionBooking extends Model
{
    /** Bookings that hold their slot. */
    public const BUSY = ['held', 'confirmed'];

    public const TAGS = ['clear', 'patient', 'knowledgeable', 'on_time', 'helpful_examples'];

    /** @var list<string> */
    protected $fillable = ['uuid', 'student_id', 'instructor_id', 'video_session_id', 'order_id', 'starts_at', 'ends_at', 'minutes', 'topic', 'status', 'price_cents', 'held_until', 'cancelled_at', 'cancelled_by', 'refunded_at', 'rating', 'rating_tags', 'rating_comment', 'rated_at', 'tasks_sent_at'];

    /** @return BelongsTo<User, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /** @return BelongsTo<User, $this> */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    /** @return BelongsTo<VideoSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(VideoSession::class, 'video_session_id');
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime', 'ends_at' => 'datetime', 'held_until' => 'datetime', 'cancelled_at' => 'datetime', 'refunded_at' => 'datetime',
            'rating_tags' => 'array', 'rated_at' => 'datetime', 'tasks_sent_at' => 'datetime',
        ];
    }
}
