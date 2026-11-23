<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Listings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Listings\MlsMemberRequest;
use App\Mls\MyListings;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * My listings (B10.1, ADR 0042): MLS listings where I'm the list or co-list
 * agent, through the MLS member records I confirmed (found by my licence).
 */
final class ListingsController extends Controller
{
    /**
     * My listings.
     *
     * Listings from my confirmed MLS member records, on the market first; member records found by my licence that I haven't answered; and whether the MLS data is sample data (Simulator).
     *
     * @response array{data: array{licensed: bool, linked: list<array{mls: string, short_name: string, member_key: string, name: string, office: string|null, office_city: string|null}>, candidates: list<array{mls: string, short_name: string, member_key: string, name: string, office: string|null, office_city: string|null}>, listings: list<array{uuid: string, mls: string, short_name: string, listing_id: string|null, status: string, role: 'list'|'co_list', address: string, city: string|null, price: int|null, close_price: int|null, beds: int|null, baths: float|null, area: int|null, photo: array{url: string, caption: string|null}|null, photos: int, listed_on: string|null, days_on_market: int|null, office: string|null, open_house: array{starts_at: string, ends_at: string, kind: string}|null}>, sample: bool, synced_at: string|null}}
     */
    public function index(#[CurrentUser] User $user, MyListings $listings): JsonResponse
    {
        return response()->json(['data' => $listings->overview($user)]);
    }

    /**
     * Confirm my MLS member record.
     *
     * "This is me": one found by my licence number. Its listings become mine.
     *
     * @response array{data: array{licensed: bool, linked: list<array{mls: string, short_name: string, member_key: string, name: string, office: string|null, office_city: string|null}>, candidates: list<array{mls: string, short_name: string, member_key: string, name: string, office: string|null, office_city: string|null}>, listings: list<array{uuid: string, mls: string, short_name: string, listing_id: string|null, status: string, role: 'list'|'co_list', address: string, city: string|null, price: int|null, close_price: int|null, beds: int|null, baths: float|null, area: int|null, photo: array{url: string, caption: string|null}|null, photos: int, listed_on: string|null, days_on_market: int|null, office: string|null, open_house: array{starts_at: string, ends_at: string, kind: string}|null}>, sample: bool, synced_at: string|null}}
     */
    public function confirm(MlsMemberRequest $request, #[CurrentUser] User $user, MyListings $listings): JsonResponse
    {
        $listings->confirm($user, $request->mls(), $request->memberKey());

        return response()->json(['data' => $listings->overview($user)]);
    }

    /**
     * Not my MLS member record.
     *
     * "Not me": REOS stops suggesting it.
     *
     * @response array{data: array{licensed: bool, linked: list<array{mls: string, short_name: string, member_key: string, name: string, office: string|null, office_city: string|null}>, candidates: list<array{mls: string, short_name: string, member_key: string, name: string, office: string|null, office_city: string|null}>, listings: list<array{uuid: string, mls: string, short_name: string, listing_id: string|null, status: string, role: 'list'|'co_list', address: string, city: string|null, price: int|null, close_price: int|null, beds: int|null, baths: float|null, area: int|null, photo: array{url: string, caption: string|null}|null, photos: int, listed_on: string|null, days_on_market: int|null, office: string|null, open_house: array{starts_at: string, ends_at: string, kind: string}|null}>, sample: bool, synced_at: string|null}}
     */
    public function decline(MlsMemberRequest $request, #[CurrentUser] User $user, MyListings $listings): JsonResponse
    {
        $listings->decline($user, $request->mls(), $request->memberKey());

        return response()->json(['data' => $listings->overview($user)]);
    }

    /**
     * Unlink my MLS member record.
     *
     * Its listings leave My listings; confirming it again brings them back.
     *
     * @response array{data: array{licensed: bool, linked: list<array{mls: string, short_name: string, member_key: string, name: string, office: string|null, office_city: string|null}>, candidates: list<array{mls: string, short_name: string, member_key: string, name: string, office: string|null, office_city: string|null}>, listings: list<array{uuid: string, mls: string, short_name: string, listing_id: string|null, status: string, role: 'list'|'co_list', address: string, city: string|null, price: int|null, close_price: int|null, beds: int|null, baths: float|null, area: int|null, photo: array{url: string, caption: string|null}|null, photos: int, listed_on: string|null, days_on_market: int|null, office: string|null, open_house: array{starts_at: string, ends_at: string, kind: string}|null}>, sample: bool, synced_at: string|null}}
     */
    public function unlink(MlsMemberRequest $request, #[CurrentUser] User $user, MyListings $listings): JsonResponse
    {
        $listings->unlink($user, $request->mls(), $request->memberKey());

        return response()->json(['data' => $listings->overview($user)]);
    }
}
