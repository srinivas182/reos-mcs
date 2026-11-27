<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An agent asking to join an accountability pod (A13.2).
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $organisation_id the agent's brokerage (null: independent)
 * @property string $state_code
 * @property string $goal
 * @property string $language
 * @property bool $part_time
 * @property CarbonImmutable|null $matched_at
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 */
final class PodRequest extends Model
{
    protected $fillable = ['user_id', 'organisation_id', 'state_code', 'goal', 'language', 'part_time', 'matched_at', 'cancelled_at'];

    protected function casts(): array
    {
        return ['part_time' => 'boolean', 'matched_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
