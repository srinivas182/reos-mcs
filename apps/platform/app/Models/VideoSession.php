<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A session in a video room (B6.3, ADR 0024): a 1-on-1 session or a live
 * class, hosted by an instructor or mentor. Join opens five minutes before
 * the start; the room closes for everyone at the booked end.
 *
 * @property int $id
 * @property string $uuid
 * @property int|null $organisation_id
 * @property int $host_id
 * @property string $kind one_on_one or live_class
 * @property string $title
 * @property string|null $agenda
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property string $status scheduled, live, ended or cancelled
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $ended_at
 * @property CarbonImmutable|null $reminded_at
 * @property string|null $notes
 * @property int|null $notes_updated_by
 * @property CarbonImmutable|null $notes_updated_at
 * @property string|null $whiteboard Excalidraw elements (JSON)
 * @property string|null $whiteboard_image
 * @property CarbonImmutable|null $whiteboard_updated_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $host
 * @property-read Collection<int, VideoSessionParticipant> $participants
 * @property-read Collection<int, VideoSessionShare> $shares
 */
final class VideoSession extends Model
{
    public const ONE_ON_ONE = 'one_on_one';

    public const LIVE_CLASS = 'live_class';

    public const SCHEDULED = 'scheduled';

    public const LIVE = 'live';

    public const ENDED = 'ended';

    public const CANCELLED = 'cancelled';

    /** Join opens this many minutes before the start. */
    public const JOIN_EARLY_MINUTES = 5;

    /** A live class: up to 100 people watching. */
    public const MAX_GUESTS = 100;

    protected $fillable = ['uuid', 'organisation_id', 'host_id', 'kind', 'title', 'agenda', 'starts_at', 'ends_at', 'status', 'started_at', 'ended_at', 'reminded_at', 'notes', 'notes_updated_by', 'notes_updated_at', 'whiteboard', 'whiteboard_image', 'whiteboard_updated_at'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'reminded_at' => 'datetime',
            'notes_updated_at' => 'datetime',
            'whiteboard_updated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    /** @return HasMany<VideoSessionParticipant, $this> */
    public function participants(): HasMany
    {
        return $this->hasMany(VideoSessionParticipant::class);
    }

    /** @return HasMany<VideoSessionShare, $this> */
    public function shares(): HasMany
    {
        return $this->hasMany(VideoSessionShare::class);
    }

    /** The LiveKit room name. */
    public function room(): string
    {
        return 'reos-'.$this->uuid;
    }

    public function joinOpensAt(): CarbonImmutable
    {
        return $this->starts_at->subMinutes(self::JOIN_EARLY_MINUTES);
    }

    public function open(): bool
    {
        return in_array($this->status, [self::SCHEDULED, self::LIVE], true)
            && now()->greaterThanOrEqualTo($this->joinOpensAt()) && now()->lessThan($this->ends_at);
    }

    public function over(): bool
    {
        return in_array($this->status, [self::ENDED, self::CANCELLED], true) || now()->greaterThanOrEqualTo($this->ends_at);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
