<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A pod's member and what they share inside it (A13.2).
 *
 * @property int $id
 * @property int $pod_id
 * @property int $user_id
 * @property list<string> $share scorecard numbers shared inside the pod
 * @property CarbonImmutable $joined_at
 * @property CarbonImmutable|null $left_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 * @property-read Pod $pod
 */
final class PodMember extends Model
{
    protected $fillable = ['pod_id', 'user_id', 'share', 'joined_at', 'left_at'];

    protected function casts(): array
    {
        return ['share' => 'array', 'joined_at' => 'immutable_datetime', 'left_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Pod, $this> */
    public function pod(): BelongsTo
    {
        return $this->belongsTo(Pod::class);
    }
}
