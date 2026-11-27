<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An agent who joined a brokerage challenge (A13.2).
 *
 * @property int $id
 * @property int $brokerage_challenge_id
 * @property int $user_id
 * @property string $baseline the same metric in the period just before
 * @property CarbonImmutable|null $left_at
 * @property-read User $user
 */
final class ChallengeEntry extends Model
{
    protected $fillable = ['brokerage_challenge_id', 'user_id', 'baseline', 'left_at'];

    protected function casts(): array
    {
        return ['left_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
