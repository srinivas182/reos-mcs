<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A milestone made into a free post (B12.3, ADR 0052; agent screen 163).
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property string $milestone just_listed, under_contract, just_sold, first_closing, ce_complete, license_renewed or anniversary
 * @property string $subject what it celebrates, so it's made once (listing:, contract:, closing:, ce:, renewal:, anniversary:)
 * @property string|null $label
 * @property int|null $studio_generation_id
 * @property int|null $social_post_id the draft waiting in My posts
 * @property CarbonImmutable|null $shown_at when it was shown (at most one a day)
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 * @property-read StudioGeneration|null $generation
 * @property-read SocialPost|null $post
 */
final class Celebration extends Model
{
    protected $fillable = ['uuid', 'user_id', 'milestone', 'subject', 'label', 'studio_generation_id', 'social_post_id', 'shown_at'];

    protected function casts(): array
    {
        return ['shown_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<StudioGeneration, $this> */
    public function generation(): BelongsTo
    {
        return $this->belongsTo(StudioGeneration::class, 'studio_generation_id');
    }

    /** @return BelongsTo<SocialPost, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class, 'social_post_id');
    }
}
