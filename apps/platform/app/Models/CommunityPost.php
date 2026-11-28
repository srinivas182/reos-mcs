<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A post in a community (B17.2), checked before it goes up.
 *
 * @property int $id
 * @property string $uuid
 * @property int $community_id
 * @property int $author_id
 * @property string $body
 * @property bool $pinned
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $author
 */
final class CommunityPost extends Model
{
    protected $fillable = ['uuid', 'community_id', 'author_id', 'body', 'pinned'];

    protected function casts(): array
    {
        return ['pinned' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
