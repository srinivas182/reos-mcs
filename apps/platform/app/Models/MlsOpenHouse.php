<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * An open house from the MLS (B10.1; on the agent's calendar in B10.2).
 *
 * @property int $id
 * @property string $integration
 * @property string $open_house_key
 * @property string $listing_key
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property string $kind public or broker
 * @property string|null $remarks
 * @property CarbonImmutable|null $modified_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class MlsOpenHouse extends Model
{
    protected $fillable = ['integration', 'open_house_key', 'listing_key', 'starts_at', 'ends_at', 'kind', 'remarks', 'modified_at'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'modified_at' => 'datetime'];
    }
}
