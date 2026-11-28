<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An area an agent farms (B14.3c): a city or ZIP code in the MLS, and the
 * tag of the contacts who get its monthly market update.
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property int|null $organisation_id
 * @property string $name
 * @property string|null $city
 * @property string|null $postal_code
 * @property string $tag
 * @property string $mailing_address
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 * @property-read Collection<int, FarmSend> $sends
 */
final class FarmArea extends Model
{
    protected $fillable = ['uuid', 'user_id', 'organisation_id', 'name', 'city', 'postal_code', 'tag', 'mailing_address'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<FarmSend, $this> */
    public function sends(): HasMany
    {
        return $this->hasMany(FarmSend::class);
    }
}
