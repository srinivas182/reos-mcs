<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An agent's answer about an MLS member record found by their licence
 * (B10.1): "this is me" (confirmed) or "not me" (declined).
 *
 * @property int $id
 * @property int $user_id
 * @property string $integration
 * @property string $member_key
 * @property string $status confirmed or declined
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 */
final class MlsMemberLink extends Model
{
    public const CONFIRMED = 'confirmed';

    public const DECLINED = 'declined';

    protected $fillable = ['user_id', 'integration', 'member_key', 'status'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
