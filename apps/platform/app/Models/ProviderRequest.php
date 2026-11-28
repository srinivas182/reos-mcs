<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A provider's request to connect with an agent (B16.1).
 *
 * @property int $id
 * @property string $uuid
 * @property int $provider_profile_id
 * @property int $provider_id
 * @property int $agent_id
 * @property int|null $provider_category_id
 * @property string|null $message
 * @property string $status pending, accepted, declined, expired or withdrawn
 * @property string $initiated_by provider (B16.1) or agent (B16.3)
 * @property Carbon $expires_at
 * @property Carbon|null $responded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ProviderProfile $profile
 * @property-read User $provider
 * @property-read User $agent
 */
final class ProviderRequest extends Model
{
    public const PENDING = 'pending';

    public const ACCEPTED = 'accepted';

    public const DECLINED = 'declined';

    public const EXPIRED = 'expired';

    public const WITHDRAWN = 'withdrawn';

    public const BY_PROVIDER = 'provider';

    public const BY_AGENT = 'agent';

    protected $fillable = ['uuid', 'provider_profile_id', 'provider_id', 'agent_id', 'provider_category_id', 'message', 'status', 'initiated_by', 'expires_at', 'responded_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'responded_at' => 'datetime'];
    }

    /** Pending past its date counts as expired, even before the daily run marks it. */
    public function state(): string
    {
        return $this->status === self::PENDING && $this->expires_at->isPast() ? self::EXPIRED : $this->status;
    }

    /** @return BelongsTo<ProviderProfile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(ProviderProfile::class, 'provider_profile_id');
    }

    /** @return BelongsTo<User, $this> */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    /** @return BelongsTo<User, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}
