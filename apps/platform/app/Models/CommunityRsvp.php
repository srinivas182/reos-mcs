<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An RSVP to an expert session (B17.2).
 *
 * @property int $id
 * @property int $community_event_id
 * @property int $user_id
 * @property string $status going, held (waiting for payment) or cancelled
 * @property Carbon|null $attended_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CommunityEvent $event
 * @property-read User $user
 */
final class CommunityRsvp extends Model
{
    protected $fillable = ['community_event_id', 'user_id', 'status', 'attended_at'];

    protected function casts(): array
    {
        return ['attended_at' => 'datetime'];
    }

    /** @return BelongsTo<CommunityEvent, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(CommunityEvent::class, 'community_event_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
