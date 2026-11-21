<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An event changed in REOS and in the tool before either side saw the other
 * (B4.1). The newest change wins; the losing version is kept here for 30
 * days and shown on the event with Undo, so nothing is overwritten silently.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $calendar_event_id
 * @property string $kept `reos` or `tool`
 * @property string $reason `edited_both`, `deleted_in_tool` or `deleted_in_reos`
 * @property array{title?: string|null, starts_at?: string|null, ends_at?: string|null, all_day?: bool, location?: string|null, description?: string|null, attendees?: list<string>, deleted?: bool, kind?: string, source?: string} $losing
 * @property Carbon|null $undone_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class CalendarConflict extends Model
{
    /** How long the losing version can be brought back. */
    public const KEEP_DAYS = 30;

    public const KEPT_REOS = 'reos';

    public const KEPT_TOOL = 'tool';

    public const EDITED_BOTH = 'edited_both';

    public const DELETED_IN_TOOL = 'deleted_in_tool';

    public const DELETED_IN_REOS = 'deleted_in_reos';

    protected $fillable = ['user_id', 'calendar_event_id', 'kept', 'reason', 'losing', 'undone_at'];

    protected function casts(): array
    {
        return [
            'losing' => 'array',
            'undone_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<CalendarEvent, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(CalendarEvent::class, 'calendar_event_id')->withTrashed();
    }

    public function expiresAt(): CarbonInterface
    {
        return ($this->created_at ?? Carbon::now())->copy()->addDays(self::KEEP_DAYS);
    }

    /** Still shown, with Undo. */
    public function open(): bool
    {
        return $this->undone_at === null && $this->expiresAt()->isFuture();
    }
}
