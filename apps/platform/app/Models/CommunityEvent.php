<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An expert session in a community (B17.2): online or in person, free, or
 * paid on Partner, with RSVPs and a clear CE note.
 *
 * @property int $id
 * @property string $uuid
 * @property int $community_id
 * @property int $host_id
 * @property string $title
 * @property string|null $about
 * @property string $format online or in_person
 * @property Carbon $starts_at
 * @property int $minutes
 * @property string|null $place
 * @property string|null $join_url
 * @property int|null $capacity
 * @property int $price_cents
 * @property int|null $product_id
 * @property string|null $ce_course The host's CE course number when it's approved elsewhere; null: not for CE
 * @property string $status scheduled, done or cancelled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Community $community
 * @property-read User $host
 * @property-read Collection<int, CommunityRsvp> $rsvps
 */
final class CommunityEvent extends Model
{
    protected $fillable = ['uuid', 'community_id', 'host_id', 'title', 'about', 'format', 'starts_at', 'minutes', 'place', 'join_url', 'capacity', 'price_cents', 'product_id', 'ce_course', 'status'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime'];
    }

    /** @return BelongsTo<Community, $this> */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /** @return BelongsTo<User, $this> */
    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    /** @return HasMany<CommunityRsvp, $this> */
    public function rsvps(): HasMany
    {
        return $this->hasMany(CommunityRsvp::class);
    }
}
