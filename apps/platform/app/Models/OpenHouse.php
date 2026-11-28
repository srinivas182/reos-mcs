<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An open house the agent runs sign-in for (B14.3a).
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property int|null $organisation_id
 * @property string $address
 * @property string|null $city
 * @property string $code
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 */
final class OpenHouse extends Model
{
    protected $fillable = ['uuid', 'user_id', 'organisation_id', 'address', 'city', 'code', 'starts_at', 'ends_at'];

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<OpenHouseVisit, $this> */
    public function visits(): HasMany
    {
        return $this->hasMany(OpenHouseVisit::class);
    }
}
