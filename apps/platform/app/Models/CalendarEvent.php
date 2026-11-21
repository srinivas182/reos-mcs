<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * One event on a person's REOS calendar (B4.1, ADR 0017): made in REOS
 * (appointments, showings, deadlines, sessions, CE dates) or synced from a
 * connected calendar. Changes made in REOS are written back to the tool.
 *
 * All-day events start at midnight UTC on their date and end at midnight
 * after the last day, as Google and Outlook store them.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $connection_id
 * @property string $source `reos`, or the register id of the tool it came from
 * @property string $kind
 * @property string|null $external_id The tool's id for it
 * @property string|null $calendar_id The tool's calendar it lives in
 * @property string|null $title
 * @property string|null $location
 * @property string|null $description
 * @property list<string>|null $attendees
 * @property string|null $related_type
 * @property string|null $related_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property bool $all_day
 * @property bool $busy
 * @property bool $notify_guests
 * @property string $status
 * @property string|null $html_link
 * @property string|null $etag
 * @property Carbon|null $remote_updated_at
 * @property Carbon|null $local_updated_at
 * @property string|null $push_state
 * @property string|null $push_error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
final class CalendarEvent extends Model
{
    use SoftDeletes;

    public const SOURCE_REOS = 'reos';

    /** Kinds made in REOS (Connections and Sync journey, screen 16), and `event` for the tool's own. */
    public const KINDS = ['appointment', 'showing', 'deadline', 'session', 'ce', 'event'];

    /** Waiting to be written to the tool. */
    public const PUSH_PENDING = 'pending';

    /** Writing failed after every retry; shown on the event with Try again. */
    public const PUSH_FAILED = 'failed';

    protected $fillable = [
        'user_id', 'connection_id', 'source', 'kind', 'external_id', 'calendar_id', 'title', 'location', 'description',
        'attendees', 'related_type', 'related_id', 'starts_at', 'ends_at', 'all_day', 'busy', 'notify_guests', 'status',
        'html_link', 'etag', 'remote_updated_at', 'local_updated_at', 'push_state', 'push_error',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'source' => self::SOURCE_REOS,
        'kind' => 'event',
        'status' => 'confirmed',
        'all_day' => false,
        'busy' => false,
        'notify_guests' => false,
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'all_day' => 'boolean',
            'busy' => 'boolean',
            'notify_guests' => 'boolean',
            'attendees' => 'array',
            'remote_updated_at' => 'datetime',
            'local_updated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Connection, $this> */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }

    /** @return HasMany<CalendarConflict, $this> */
    public function conflicts(): HasMany
    {
        return $this->hasMany(CalendarConflict::class);
    }

    public function madeInReos(): bool
    {
        return $this->source === self::SOURCE_REOS;
    }

    public function pending(): bool
    {
        return $this->push_state === self::PUSH_PENDING;
    }

    /** Changed in REOS and not yet in the tool (waiting, or failed and waiting for Try again). */
    public function unsent(): bool
    {
        return in_array($this->push_state, [self::PUSH_PENDING, self::PUSH_FAILED], true);
    }

    /**
     * The fields people edit, as a version to keep when it loses a conflict.
     *
     * @return array{title: string|null, starts_at: string, ends_at: string, all_day: bool, location: string|null, description: string|null, attendees: list<string>, deleted: bool}
     */
    public function version(): array
    {
        return [
            'title' => $this->title,
            'starts_at' => $this->starts_at->toIso8601String(),
            'ends_at' => $this->ends_at->toIso8601String(),
            'all_day' => $this->all_day,
            'location' => $this->location,
            'description' => $this->description,
            'attendees' => $this->attendees ?? [],
            'deleted' => $this->trashed(),
        ];
    }
}
