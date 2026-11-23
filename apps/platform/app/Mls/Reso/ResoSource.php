<?php

declare(strict_types=1);

namespace App\Mls\Reso;

use App\Connections\Exceptions\AccessRevoked;
use App\Connections\Exceptions\VendorRateLimited;
use App\Connections\Exceptions\VendorUnavailable;

/**
 * Where an MLS's RESO records come from: the vendor's Web API, or the
 * Simulator's demo listings. Both hand back raw RESO records, so the same
 * mapping ({@see ResoRecords}) reads either.
 */
interface ResoSource
{
    public const RESOURCES = ['Office', 'Member', 'Property', 'OpenHouse'];

    /** `simulator` or `reso`; data from one is never mixed with the other. */
    public function id(): string;

    /**
     * Records of a resource changed at or after `$since` (null: all), oldest
     * first. Pass the page's `next` back to carry on.
     *
     * @param  'Office'|'Member'|'Property'|'OpenHouse'  $resource
     *
     * @throws AccessRevoked|VendorRateLimited|VendorUnavailable
     */
    public function changes(string $resource, ?string $since, ?string $next): ResoPage;

    /**
     * Every ListingKey the MLS still has, a page at a time, to find
     * listings deleted at the source.
     *
     * @throws AccessRevoked|VendorRateLimited|VendorUnavailable
     */
    public function listingKeys(?string $next): ResoPage;
}
