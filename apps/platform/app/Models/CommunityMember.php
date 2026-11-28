<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member of a community (B17.2): the owner, or someone who joined by link or QR code.
 *
 * @property int $id
 * @property int $community_id
 * @property int $user_id
 * @property string $role owner or member
 * @property string $via owner, link or qr
 * @property Carbon $joined_at
 * @property Carbon|null $left_at
 * @property-read Community $community
 * @property-read User $user
 */
final class CommunityMember extends Model
{
    public $timestamps = false;

    protected $fillable = ['community_id', 'user_id', 'role', 'via', 'joined_at', 'left_at'];

    protected function casts(): array
    {
        return ['joined_at' => 'datetime', 'left_at' => 'datetime'];
    }

    /** @return BelongsTo<Community, $this> */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
