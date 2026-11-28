<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Where a provider works (B15.2): a city, a ZIP code, or a radius in miles
 * around a ZIP code.
 *
 * @property int $id
 * @property int $provider_profile_id
 * @property string $kind city, zip or radius
 * @property string|null $city
 * @property string|null $postal_code
 * @property int|null $radius_miles
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class ProviderServiceArea extends Model
{
    public const KINDS = ['city', 'zip', 'radius'];

    protected $fillable = ['provider_profile_id', 'kind', 'city', 'postal_code', 'radius_miles'];

    protected function casts(): array
    {
        return ['radius_miles' => 'integer'];
    }
}
