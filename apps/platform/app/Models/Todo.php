<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A person's to-do, typed or spoken (B4.3, ADR 0018).
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $client_id The phone's own id for it, so a capture sent twice is saved once
 * @property string $title
 * @property string|null $notes
 * @property Carbon|null $due_at When it's due, with a time
 * @property Carbon|null $due_date When it's due, a date with no time
 * @property Carbon|null $remind_at
 * @property string|null $related_type `phone_contact` today; CRM contacts from Sprint 5
 * @property string|null $related_id
 * @property string|null $related_label The person's name, as shown
 * @property string $source `typed` or `voice`
 * @property Carbon|null $done_at
 * @property int|null $calendar_event_id The due date on the person's calendar
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
final class Todo extends Model
{
    use SoftDeletes;

    public const SOURCES = ['typed', 'voice'];

    protected $fillable = [
        'user_id', 'client_id', 'title', 'notes', 'due_at', 'due_date', 'remind_at',
        'related_type', 'related_id', 'related_label', 'source', 'done_at', 'calendar_event_id',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['source' => 'typed'];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'due_date' => 'date',
            'remind_at' => 'datetime',
            'done_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<CalendarEvent, $this> */
    public function calendarEvent(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class)->withTrashed();
    }

    public function done(): bool
    {
        return $this->done_at !== null;
    }
}
