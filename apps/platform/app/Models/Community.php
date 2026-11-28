<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A provider's community of verified agents and providers (B17.2).
 *
 * @property int $id
 * @property string $uuid
 * @property int $owner_id
 * @property string $name
 * @property string|null $about
 * @property string $invite_code
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $owner
 * @property-read Collection<int, CommunityMember> $members
 */
final class Community extends Model
{
    protected $fillable = ['uuid', 'owner_id', 'name', 'about', 'invite_code'];

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return HasMany<CommunityMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(CommunityMember::class);
    }
}
