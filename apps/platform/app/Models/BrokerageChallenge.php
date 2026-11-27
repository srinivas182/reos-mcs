<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A brokerage challenge with an opt-in leaderboard (A13.2).
 *
 * @property int $id
 * @property string $uuid
 * @property int $organisation_id
 * @property string $title
 * @property string $metric conversations, appointments, ce_hours or posts
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property int|null $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, ChallengeEntry> $entries
 */
final class BrokerageChallenge extends Model
{
    protected $fillable = ['uuid', 'organisation_id', 'title', 'metric', 'starts_on', 'ends_on', 'created_by'];

    protected function casts(): array
    {
        return ['starts_on' => 'immutable_date', 'ends_on' => 'immutable_date', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }

    /** @return HasMany<ChallengeEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(ChallengeEntry::class)->whereNull('left_at');
    }
}
