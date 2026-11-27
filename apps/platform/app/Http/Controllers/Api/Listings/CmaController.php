<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Listings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Listings\AgentPageRequest;
use App\Http\Requests\Listings\CmaRequest;
use App\Mls\AgentPages;
use App\Mls\Cmas;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * CMAs for my listings and my public page (B10.2b, ADR 0042), both read
 * through the listing service with each MLS's display rules.
 */
final class CmaController extends Controller
{
    /**
     * A CMA for one of my listings.
     *
     * Sold (within `months`) and active comparables from the same MLS within `radius` miles, nearest first; each one's price per square foot applied to my listing's size, and the range (25th to 75th percentile) from what sold.
     *
     * @response array{data: array{subject: array{uuid: string, mls: string, short_name: string, listing_id: string|null, status: string, address: string, city: string|null, postal_code: string|null, price: int|null, close_price: int|null, close_date: string|null, beds: int|null, baths: float|null, area: int|null, price_per_sqft: int|null, photo: array{url: string, caption: string|null}|null, photos: list<array{url: string, caption: string|null}>, listed_on: string|null, days_on_market: int|null, office: string|null, latitude: float|null, longitude: float|null, attribution: string, logo_url: string|null, sample: bool}, radius: float, months: int, comparables: list<array{listing: array{uuid: string, mls: string, short_name: string, listing_id: string|null, status: string, address: string, city: string|null, postal_code: string|null, price: int|null, close_price: int|null, close_date: string|null, beds: int|null, baths: float|null, area: int|null, price_per_sqft: int|null, photo: array{url: string, caption: string|null}|null, photos: list<array{url: string, caption: string|null}>, listed_on: string|null, days_on_market: int|null, office: string|null, latitude: float|null, longitude: float|null, attribution: string, logo_url: string|null, sample: bool}, distance: float, adjusted: int|null}>, sold: int, active: int, median_per_sqft: int|null, low: int|null, high: int|null, suggested: int|null, sample: bool}}
     */
    public function show(CmaRequest $request, string $listing, #[CurrentUser] User $user, Cmas $cmas): JsonResponse
    {
        return response()->json(['data' => $cmas->for($user, $listing, $request->radius(), $request->months())]);
    }

    /**
     * My public page.
     *
     * Whether it's published, its address, and my short bio.
     *
     * @response array{data: array{handle: string, published: bool, bio: string|null, url: string, waiting: bool, note: string|null}}
     */
    public function page(#[CurrentUser] User $user, AgentPages $pages): JsonResponse
    {
        return response()->json(['data' => $pages->settings($user)]);
    }

    /**
     * Publish or hide my public page.
     *
     * It shows my name, brokerage, license numbers, bio and MLS listings, as each MLS allows.
     *
     * @response array{data: array{handle: string, published: bool, bio: string|null, url: string, waiting: bool, note: string|null}}
     */
    public function updatePage(AgentPageRequest $request, #[CurrentUser] User $user, AgentPages $pages): JsonResponse
    {
        return response()->json(['data' => $pages->update($user, $request->boolean('published'), $request->bio())]);
    }
}
