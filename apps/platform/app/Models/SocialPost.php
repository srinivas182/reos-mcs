<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An agent's social post (B11, ADR 0046).
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property string $body
 * @property list<array{url: string, kind: string, path?: string}> $media
 * @property string|null $link
 * @property string $attribution
 * @property int|null $listing_post_id
 * @property string $status draft, approval (waiting for the broker, B12.1), scheduled, publishing, published, partly or failed
 * @property CarbonImmutable|null $scheduled_at
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $approval_requested_at
 * @property int|null $approved_by
 * @property CarbonImmutable|null $approved_at
 * @property string|null $approval_note what the broker said when sending it back
 * @property string|null $approval_reason the agent's reason for sending flagged wording to the broker (B13.2)
 * @property CarbonImmutable|null $planned_on a draft placed on a day of the content calendar (B12.3)
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read User $user
 * @property-read Collection<int, SocialPostTarget> $targets
 */
final class SocialPost extends Model
{
    use SoftDeletes;

    public const STATUSES = ['draft', 'approval', 'scheduled', 'publishing', 'published', 'partly', 'failed'];

    protected $fillable = ['uuid', 'user_id', 'body', 'media', 'link', 'attribution', 'listing_post_id', 'status', 'scheduled_at', 'published_at', 'approval_requested_at', 'approved_by', 'approved_at', 'approval_note', 'approval_reason', 'planned_on'];

    protected $attributes = ['media' => '[]'];

    protected function casts(): array
    {
        return [
            'media' => 'array', 'scheduled_at' => 'immutable_datetime', 'published_at' => 'immutable_datetime', 'approval_requested_at' => 'immutable_datetime', 'approved_at' => 'immutable_datetime', 'planned_on' => 'immutable_date',
            'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime', 'deleted_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<SocialPostTarget, $this> */
    public function targets(): HasMany
    {
        return $this->hasMany(SocialPostTarget::class);
    }
}
