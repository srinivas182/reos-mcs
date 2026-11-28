<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An agent's review of a provider they connected with (B16.3).
 *
 * @property int $id
 * @property string $uuid
 * @property int $provider_profile_id
 * @property int $agent_id
 * @property int $provider_request_id
 * @property int $rating 1 to 5
 * @property string|null $body
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $agent
 */
final class ProviderReview extends Model
{
    protected $fillable = ['uuid', 'provider_profile_id', 'agent_id', 'provider_request_id', 'rating', 'body'];

    protected function casts(): array
    {
        return ['rating' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}
