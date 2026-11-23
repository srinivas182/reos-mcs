<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Listings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Listings\ListingPostRequest;
use App\Mls\ListingPosts;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * A post about one of my MLS listings (B10.2, ADR 0042): only my own
 * listings, only photos the MLS allows, and the MLS's attribution on every post.
 */
final class ListingPostController extends Controller
{
    /**
     * My post for a listing.
     *
     * The photos the MLS lets a post use (at most its limit, and 10), the caption so far (or a suggestion), the chosen photos, and the attribution added to every post.
     *
     * @response array{data: array{listing: string, address: string, photos: list<array{url: string, caption: string|null}>, max_photos: int, caption: string, chosen: list<string>, attribution: string, saved_at: string|null, sample: bool}}
     */
    public function show(string $listing, #[CurrentUser] User $user, ListingPosts $posts): JsonResponse
    {
        return response()->json(['data' => $posts->draft($user, $listing)]);
    }

    /**
     * Save my post for a listing.
     *
     * The caption and the chosen photos, in order; photos must be among those the MLS allows.
     *
     * @response array{data: array{listing: string, address: string, photos: list<array{url: string, caption: string|null}>, max_photos: int, caption: string, chosen: list<string>, attribution: string, saved_at: string|null, sample: bool}}
     */
    public function update(ListingPostRequest $request, string $listing, #[CurrentUser] User $user, ListingPosts $posts): JsonResponse
    {
        return response()->json(['data' => $posts->save($user, $listing, $request->caption(), $request->photos())]);
    }
}
