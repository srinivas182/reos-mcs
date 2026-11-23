<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * An agent's post about one of their MLS listings (B10.2), within the MLS's
 * licence terms. B11 publishes it to the agent's social channels.
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property string $integration
 * @property string $listing_key
 * @property string $caption
 * @property list<string> $photos
 * @property string $attribution
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class ListingPost extends Model
{
    protected $fillable = ['uuid', 'user_id', 'integration', 'listing_key', 'caption', 'photos', 'attribution'];

    protected function casts(): array
    {
        return ['photos' => 'array'];
    }
}
