<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One place a social post goes (B11): a Facebook Page, an Instagram
 * account, a LinkedIn profile… with its own status and insights.
 *
 * @property int $id
 * @property int $social_post_id
 * @property int|null $connection_id
 * @property string $integration
 * @property string $channel
 * @property string $target_id
 * @property string $target_name
 * @property string $status queued, publishing, published or failed
 * @property int $attempts
 * @property string|null $last_error
 * @property string|null $external_id
 * @property string|null $url
 * @property CarbonImmutable|null $published_at
 * @property array<string, int>|null $insights
 * @property CarbonImmutable|null $insights_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read SocialPost $post
 * @property-read Connection|null $connection
 */
final class SocialPostTarget extends Model
{
    protected $fillable = ['social_post_id', 'connection_id', 'integration', 'channel', 'target_id', 'target_name', 'status', 'attempts', 'last_error', 'external_id', 'url', 'published_at', 'insights', 'insights_at'];

    protected function casts(): array
    {
        return ['insights' => 'array', 'published_at' => 'immutable_datetime', 'insights_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<SocialPost, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(SocialPost::class, 'social_post_id');
    }

    /** @return BelongsTo<Connection, $this> */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }
}
