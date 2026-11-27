<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What a brokerage sets for one of its mentors (A13.1a, broker screen 22).
 *
 * @property int $id
 * @property int $organisation_id
 * @property int $user_id
 * @property list<string> $topics
 * @property int $price_30_cents
 * @property int $price_60_cents
 * @property bool $points whether sessions can be paid with points
 * @property int $max_mentees
 * @property string $who everyone or assigned
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 * @property-read Organisation $organisation
 */
final class MentorSetting extends Model
{
    public const WHO = ['everyone', 'assigned'];

    protected $fillable = ['organisation_id', 'user_id', 'topics', 'price_30_cents', 'price_60_cents', 'points', 'max_mentees', 'who'];

    protected function casts(): array
    {
        return ['topics' => 'array', 'points' => 'boolean', 'price_30_cents' => 'integer', 'price_60_cents' => 'integer', 'max_mentees' => 'integer', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
