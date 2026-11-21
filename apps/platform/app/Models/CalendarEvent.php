<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An event synced from a connected calendar (read-only until B4.1 makes it two-way).
 *
 * @property int $id
 * @property int $connection_id
 * @property string $external_id
 * @property string $calendar_id
 * @property string|null $title
 * @property string|null $location
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property bool $all_day
 * @property string $status
 * @property string|null $html_link
 * @property Carbon|null $remote_updated_at
 */
final class CalendarEvent extends Model
{
    protected $fillable = ['connection_id', 'external_id', 'calendar_id', 'title', 'location', 'starts_at', 'ends_at', 'all_day', 'status', 'html_link', 'remote_updated_at'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'all_day' => 'boolean',
            'remote_updated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Connection, $this> */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }
}
