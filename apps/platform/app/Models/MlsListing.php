<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A listing from an MLS feed (B10.1), as the MLS published it. Platform-wide:
 * one copy serves every agent; screens reach it through the listing service.
 *
 * @property int $id
 * @property string $uuid
 * @property string $integration
 * @property string $listing_key
 * @property string|null $listing_id
 * @property string $status coming_soon, active, under_contract, pending or closed
 * @property string|null $property_type
 * @property string|null $property_sub_type
 * @property string|null $street
 * @property string|null $unit
 * @property string|null $city
 * @property string|null $state
 * @property string|null $postal_code
 * @property string|null $subdivision
 * @property string|null $latitude
 * @property string|null $longitude
 * @property int|null $list_price
 * @property int|null $original_list_price
 * @property int|null $close_price
 * @property CarbonImmutable|null $close_date
 * @property int|null $bedrooms
 * @property string|null $bathrooms
 * @property int|null $living_area
 * @property string|null $lot_acres
 * @property int|null $year_built
 * @property string|null $list_agent_key
 * @property string|null $list_agent_name
 * @property string|null $co_list_agent_key
 * @property string|null $list_office_key
 * @property string|null $list_office_name
 * @property string|null $remarks
 * @property list<array{url: string, caption: string|null}> $photos
 * @property string|null $virtual_tour
 * @property CarbonImmutable|null $listed_on
 * @property bool $idx
 * @property bool $address_shown
 * @property CarbonImmutable|null $modified_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class MlsListing extends Model
{
    /** Shown first: on the market, then under contract, then sold. */
    public const ORDER = ['coming_soon', 'active', 'under_contract', 'pending', 'closed'];

    protected $fillable = ['uuid', 'integration', 'listing_key', 'listing_id', 'status', 'property_type', 'property_sub_type', 'street', 'unit', 'city', 'state', 'postal_code', 'subdivision', 'latitude', 'longitude', 'list_price', 'original_list_price', 'close_price', 'close_date', 'bedrooms', 'bathrooms', 'living_area', 'lot_acres', 'year_built', 'list_agent_key', 'list_agent_name', 'co_list_agent_key', 'list_office_key', 'list_office_name', 'remarks', 'photos', 'virtual_tour', 'listed_on', 'idx', 'address_shown', 'modified_at'];

    protected function casts(): array
    {
        return [
            'list_price' => 'integer', 'original_list_price' => 'integer', 'close_price' => 'integer', 'close_date' => 'immutable_date',
            'bedrooms' => 'integer', 'living_area' => 'integer', 'year_built' => 'integer', 'photos' => 'array', 'listed_on' => 'immutable_date',
            'idx' => 'boolean', 'address_shown' => 'boolean', 'modified_at' => 'datetime',
        ];
    }

    /** @return HasMany<MlsOpenHouse, $this> */
    public function openHouses(): HasMany
    {
        return $this->hasMany(MlsOpenHouse::class, 'listing_key', 'listing_key')->whereColumn('mls_open_houses.integration', 'mls_listings.integration');
    }
}
