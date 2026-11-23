<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A live class (A10.2b, ADR 0040): a video session with a course and a
 * capacity. A CE class carries the attendance rules of the course's live
 * approval, copied when it was scheduled.
 *
 * @property int $id
 * @property int $video_session_id
 * @property int $course_id
 * @property int|null $course_approval_id
 * @property int $capacity
 * @property int|null $checkin_minutes
 * @property int|null $presence_percent
 * @property CarbonImmutable|null $reminded_hour_at
 * @property string $report_status none, draft or sent (A10.2c)
 * @property int|null $report_sent_by
 * @property CarbonImmutable|null $report_sent_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read VideoSession $session
 * @property-read Course $course
 * @property-read CourseApproval|null $approval
 */
final class LiveClass extends Model
{
    /** Classes have room for at most this many students. */
    public const MAX_CAPACITY = VideoSession::MAX_GUESTS;

    public const REPORT_NONE = 'none';

    public const REPORT_DRAFT = 'draft';

    public const REPORT_SENT = 'sent';

    protected $fillable = ['video_session_id', 'course_id', 'course_approval_id', 'capacity', 'checkin_minutes', 'presence_percent', 'reminded_hour_at', 'report_status', 'report_sent_by', 'report_sent_at'];

    protected function casts(): array
    {
        return ['capacity' => 'integer', 'checkin_minutes' => 'integer', 'presence_percent' => 'integer', 'reminded_hour_at' => 'datetime', 'report_sent_at' => 'datetime'];
    }

    /** @return BelongsTo<VideoSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(VideoSession::class, 'video_session_id');
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return BelongsTo<CourseApproval, $this> */
    public function approval(): BelongsTo
    {
        return $this->belongsTo(CourseApproval::class, 'course_approval_id');
    }

    /** A CE class: attendance is checked and reported. */
    public function ce(): bool
    {
        return $this->course_approval_id !== null && $this->checkin_minutes !== null;
    }
}
